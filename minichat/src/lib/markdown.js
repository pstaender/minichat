import DOMPurify from 'dompurify'
import { marked } from 'marked'

marked.setOptions({ breaks: true, gfm: true })

export function renderMarkdown(text) {
  const html = marked.parse(text ?? '')
  return DOMPurify.sanitize(html, { ADD_ATTR: ['target', 'rel'] })
}
