<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Exceptions\NotFoundException;
use SevenCivilGroup\Core\Exceptions\ValidationException;
use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Entities\Template;
use SevenCivilGroup\Domain\Enums\PaperSize;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Repositories\TemplateRepositoryInterface;
use SevenCivilGroup\Services\AccessControl;

/**
 * قالب‌ساز — manage reusable letter templates. Listing needs templates.view;
 * create/update/delete need templates.manage (managers & executive).
 */
final class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templates,
        private readonly AccessControl $access,
    ) {
    }

    /** GET /api/templates */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $this->access->authorize($user, Permission::TemplatesView);

        return Response::ok([
            'items' => array_map($this->present(...), $this->templates->all()),
            'can_manage' => $this->access->can($user, Permission::TemplatesManage),
        ]);
    }

    /** POST /api/templates */
    public function store(Request $request): Response
    {
        $user = $this->user($request);
        $this->access->authorize($user, Permission::TemplatesManage);

        $template = $this->templates->save($this->fromRequest($request, 0, $user->id));

        return Response::created($this->present($template), 'قالب جدید ذخیره شد.');
    }

    /** PUT /api/templates/{id} */
    public function update(Request $request): Response
    {
        $user = $this->user($request);
        $this->access->authorize($user, Permission::TemplatesManage);
        $id = (int) $request->attribute('id');
        $this->templates->find($id) ?? throw new NotFoundException('قالب یافت نشد.');

        $template = $this->templates->save($this->fromRequest($request, $id, $user->id));

        return Response::ok($this->present($template), 'قالب به‌روزرسانی شد.');
    }

    /** POST /api/templates/{id}/duplicate */
    public function duplicate(Request $request): Response
    {
        $user = $this->user($request);
        $this->access->authorize($user, Permission::TemplatesManage);
        $src = $this->templates->find((int) $request->attribute('id')) ?? throw new NotFoundException('قالب یافت نشد.');

        $copy = new Template(0, $src->title . ' (رونوشت)', $src->body, $src->paperSize, $src->departmentId, $user->id, $src->category);
        $template = $this->templates->save($copy);

        return Response::created($this->present($template), 'رونوشت قالب ساخته شد.');
    }

    /** DELETE /api/templates/{id} */
    public function destroy(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::TemplatesManage);
        $this->templates->delete((int) $request->attribute('id'));

        return Response::ok(null, 'قالب حذف شد.');
    }

    private function fromRequest(Request $request, int $id, int $userId): Template
    {
        $title = trim((string) $request->input('title', ''));
        $body = (string) $request->input('body', '');
        if ($title === '') {
            throw new ValidationException(['title' => 'عنوان قالب الزامی است.']);
        }
        if (trim(strip_tags($body)) === '') {
            throw new ValidationException(['body' => 'متن قالب نمی‌تواند خالی باشد.']);
        }
        $paper = PaperSize::tryFrom((string) $request->input('paper_size', 'a4')) ?? PaperSize::A4;
        $category = trim((string) $request->input('category', '')) ?: null;

        return new Template($id, $title, $this->sanitize($body), $paper, $this->intOrNull($request->input('department_id')), $userId, $category);
    }

    /** Tags kept by the sanitizer; everything else (script, img, iframe, a, …) is dropped. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'span', 'div',
        'h1', 'h2', 'h3', 'h4', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
    ];

    /**
     * Allow-list HTML sanitizer (DOMDocument-based, not regex): keeps only a set
     * of formatting tags, strips ALL attributes (so no on*=, style, href,
     * javascript: survive) and removes comments. Robust against the classic
     * `<img/src=x/onerror=…>` style bypasses.
     */
    private function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root');
        if ($root === null) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }
        $this->cleanNode($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private function cleanNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue; // text nodes are safe
            }
            if (!in_array(strtolower($child->tagName), self::ALLOWED_TAGS, true)) {
                $node->removeChild($child); // drop disallowed elements entirely
                continue;
            }
            // Strip every attribute from allowed elements.
            while ($child->attributes->length > 0) {
                $child->removeAttribute($child->attributes->item(0)->nodeName);
            }
            $this->cleanNode($child);
        }
    }

    /** @return array<string,mixed> */
    private function present(Template $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'body' => $t->body,
            'category' => $t->category,
            'paper_size' => $t->paperSize->value,
            'department_id' => $t->departmentId,
        ];
    }

    private function intOrNull(mixed $v): ?int
    {
        return ($v === null || $v === '' || (int) $v === 0) ? null : (int) $v;
    }
}
