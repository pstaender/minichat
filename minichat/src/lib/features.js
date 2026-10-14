import config from '../../config.json'

// Sidebar sections from minichat/config.json. A value is either `true` or an
// object (room for per-feature options); anything truthy enables it.
export const chatEnabled = Boolean(config.chat)
export const roleChatEnabled = Boolean(config.roleChat)
export const shareableChatsEnabled = Boolean(config.shareableChats)
