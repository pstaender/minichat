import TurndownService from 'turndown'

const turndownService = new TurndownService({ headingStyle: 'atx', codeBlockStyle: 'fenced' })

/**
 * Reads a clipboard paste event and returns plain text, converting rich
 * HTML clipboard content (e.g. pasted from a web page) into Markdown.
 */
export function textFromClipboard(event) {
  const html = event.clipboardData?.getData('text/html')
  if (html && html.trim() !== '') {
    return turndownService.turndown(html)
  }
  return event.clipboardData?.getData('text/plain') ?? ''
}
