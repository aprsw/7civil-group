// Thin fetch-based JSON API client (no axios dependency). Attaches the bearer
// token, unwraps the { success, message, data } envelope, and throws a rich
// error carrying the server's Persian message + validation errors.

const BASE = import.meta.env.VITE_API_BASE || '/api'

let authToken = localStorage.getItem('civil7_token') || sessionStorage.getItem('civil7_token') || null

// persist=true → localStorage (survives restart, "remember me"); false → sessionStorage.
export function setToken(token, persist = true) {
  authToken = token
  localStorage.removeItem('civil7_token')
  sessionStorage.removeItem('civil7_token')
  if (token) (persist ? localStorage : sessionStorage).setItem('civil7_token', token)
}

export class ApiError extends Error {
  constructor(message, status, errors) {
    super(message)
    this.status = status
    this.errors = errors || {}
  }
}

async function request(method, path, body) {
  const headers = { 'Content-Type': 'application/json' }
  if (authToken) headers.Authorization = `Bearer ${authToken}`

  const res = await fetch(`${BASE}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  let payload = null
  if (res.status !== 204) {
    try {
      payload = await res.json()
    } catch {
      payload = null
    }
  }

  // An authenticated call that comes back 401 means the session expired/was
  // revoked → tell the app to log out (not fired for the login call itself).
  if (res.status === 401 && authToken) {
    window.dispatchEvent(new CustomEvent('civil7:unauthorized'))
  }

  if (!res.ok || (payload && payload.success === false)) {
    const message = payload?.message || 'خطا در ارتباط با سرور رخ داد.'
    throw new ApiError(message, res.status, payload?.errors)
  }

  return payload ? payload.data : null
}

// Multipart upload (used for letter attachments and logos). `fields` carries
// any extra text parts (e.g. a logo's title). Returns the `data` envelope.
async function upload(path, file, fields = {}) {
  const form = new FormData()
  form.append('file', file)
  for (const [key, value] of Object.entries(fields)) {
    if (value !== undefined && value !== null) form.append(key, value)
  }
  const headers = {}
  if (authToken) headers.Authorization = `Bearer ${authToken}`

  const res = await fetch(`${BASE}${path}`, { method: 'POST', headers, body: form })
  let payload = null
  try {
    payload = await res.json()
  } catch {
    payload = null
  }
  if (!res.ok || (payload && payload.success === false)) {
    throw new ApiError(payload?.message || 'بارگذاری فایل ناموفق بود.', res.status, payload?.errors)
  }
  return payload ? payload.data : null
}

// Authenticated file download → triggers a browser save with the real filename.
async function download(path, fallbackName = 'attachment') {
  const headers = {}
  if (authToken) headers.Authorization = `Bearer ${authToken}`
  const res = await fetch(`${BASE}${path}`, { headers })
  if (!res.ok) throw new ApiError('دانلود پیوست ناموفق بود.', res.status)

  const disposition = res.headers.get('Content-Disposition') || ''
  const match = disposition.match(/filename="?([^"]+)"?/)
  const name = match ? decodeURIComponent(match[1]) : fallbackName

  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = name
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

// Authenticated fetch that returns the raw bytes (for in-browser parsing).
async function fetchBytes(path) {
  const headers = {}
  if (authToken) headers.Authorization = `Bearer ${authToken}`
  const res = await fetch(`${BASE}${path}`, { headers })
  if (!res.ok) throw new ApiError('دریافت فایل ناموفق بود.', res.status)
  return await res.arrayBuffer()
}

export const api = {
  get: (path) => request('GET', path),
  post: (path, body) => request('POST', path, body),
  put: (path, body) => request('PUT', path, body),
  del: (path) => request('DELETE', path),
  upload,
  download,
  fetchBytes,
}
