(function() {
    'use strict';

    const CSS_STYLES = `
#quake-console {
    position: fixed;
    top: -50vh;
    left: 0;
    right: 0;
    width: 100%;
    height: 50vh;
    background: rgba(0, 0, 0, 0.95);
    color: #00ff00;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    transition: top 0.3s ease-out;
    border-bottom: 1px solid #00ff00;
}
#quake-console.open {
    top: 0;
}
#chat-output {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    background: rgba(0, 0, 0, 0.5);
    scrollbar-width: thin;
    scrollbar-color: #00ff00 rgba(0, 0, 0, 0.3);
}
#chat-output::-webkit-scrollbar {
    width: 6px;
}
#chat-output::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.3);
}
#chat-output::-webkit-scrollbar-thumb {
    background: #00ff00;
}
.chat-message {
    margin-bottom: 4px;
    line-height: 1.4;
    word-wrap: break-word;
}
.chat-separator {
    margin: 10px 0;
    padding: 8px 0;
    border-top: 1px solid #00aa00;
    border-bottom: 1px solid #00aa00;
    text-align: center;
    color: #00aa00;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.chat-separator span {
    background: rgba(0, 0, 0, 0.8);
    padding: 0 10px;
}
.chat-message-system {
    color: #ffaa00;
    font-style: italic;
}
.chat-message-user {
    color: #00ff00;
}
.chat-message-own {
    background: rgba(0, 128, 0, 0.15);
    padding: 2px 5px;
    border-left: 3px solid #00ff00;
    margin-left: -5px;
    padding-left: 7px;
}
.chat-message-mention {
    background: rgba(255, 170, 0, 0.2);
    padding: 2px 5px;
    border-left: 3px solid #ffaa00;
    margin-left: -5px;
    padding-left: 7px;
    animation: mentionPulse 1s ease-out;
}
@keyframes mentionPulse {
    0% { background: rgba(255, 170, 0, 0.4); }
    100% { background: rgba(255, 170, 0, 0.2); }
}
.chat-timestamp {
    color: #888;
    margin-right: 5px;
}
.chat-user {
    color: #00aaff;
    font-weight: bold;
    margin-right: 5px;
}
.chat-user-own {
    color: #00ff00;
    font-weight: bold;
    margin-right: 5px;
}
.chat-text {
    color: #00ff00;
}
.chat-text-mention {
    color: #ffaa00;
    font-weight: bold;
}
#console-input {
    background: rgba(0, 0, 0, 0.8);
    padding: 8px 10px;
    border-top: 1px solid #00ff00;
    flex-shrink: 0;
}
#console-input-line {
    display: flex;
    align-items: center;
    gap: 8px;
}
#nickname-display {
    color: #00ff00;
    font-weight: bold;
    white-space: nowrap;
    cursor: pointer;
    transition: color 0.2s;
}
#nickname-display:hover {
    color: #00ff00;
    text-decoration: underline;
}
#nickname-display::before {
    content: '[';
    color: #00aa00;
}
#nickname-display::after {
    content: ']';
    color: #00aa00;
}
#chat-message-input {
    flex: 1;
    background: transparent;
    border: none;
    color: #00ff00;
    padding: 4px 0;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}
#chat-message-input:focus {
    outline: none;
}
#chat-message-input::placeholder {
    color: #00aa00;
    opacity: 0.6;
}
@media screen and (max-width: 768px) {
    #quake-console {
        height: 60vh;
        top: -60vh;
        font-size: 11px;
    }
    #nickname-display {
        font-size: 11px;
    }
    #chat-message-input {
        font-size: 11px;
    }
}
`;

    let consoleOpen = false;
    let nickname = localStorage.getItem('irc_nickname') || generateDefaultNickname();

    const IRC_SERVER = window.location.protocol === 'https:' ? 'wss://irc.websocket.localhost:8097/' : 'ws://irc.websocket.localhost:8097/';
    const IRC_CHANNEL = '#deadach';

    let ws = null;
    let isConnected = false;
    let reconnectTimeout = null;
    let reconnectAttempts = 0;
    const MAX_RECONNECT_ATTEMPTS = 5;
    let hasConnectedBefore = false;
    let isFirstOpen = true;
    let separatorTimeout = null;
    let messageCountBeforeSeparator = 0;
    let waitingForSeparator = false;
    let messageBuffer = [];
    let flushTimeout = null;
    let historyDebounceTimeout = null;
    const HISTORY_DEBOUNCE_DELAY = 500;

    const elements = {
        console: null,
        chatOutput: null,
        messageInput: null,
        nicknameDisplay: null
    };

    function generateDefaultNickname() {
        const randomNum = Math.floor(Math.random() * 90000) + 10000;
        return 'Anonymous' + randomNum;
    }

    function injectCSS() {
        const styleId = 'quake-console-styles';
        if (document.getElementById(styleId)) return;

        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = CSS_STYLES;
        document.head.appendChild(style);
    }

    function createConsoleHTML() {
        if (document.getElementById('quake-console')) return;

        const consoleHTML = `
            <div id="quake-console">
                <div id="chat-output"></div>
                <div id="console-input">
                    <div id="console-input-line">
                        <span id="nickname-display"></span>
                        <input type="text" id="chat-message-input" placeholder="message..." maxlength="500">
                    </div>
                </div>
            </div>
        `;

        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = consoleHTML.trim();
        document.body.insertBefore(tempDiv.firstChild, document.body.firstChild);
    }

    function initConsole() {
        injectCSS();
        createConsoleHTML();

        elements.console = document.getElementById('quake-console');
        if (!elements.console) return;

        elements.chatOutput = document.getElementById('chat-output');
        elements.messageInput = document.getElementById('chat-message-input');
        elements.nicknameDisplay = document.getElementById('nickname-display');

        if (elements.nicknameDisplay) {
            elements.nicknameDisplay.textContent = nickname;
            elements.nicknameDisplay.style.cursor = 'pointer';
            elements.nicknameDisplay.title = 'Click to change nickname';

            elements.nicknameDisplay.addEventListener('click', function() {
                const newNick = prompt('Enter new nickname:', nickname);
                if (newNick && newNick.trim() && newNick.trim() !== nickname) {
                    changeNickname(newNick.trim());
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.code === 'Backquote') {
                e.preventDefault();
                toggleConsole();
            }

            if (consoleOpen && e.key === 'Escape') {
                e.preventDefault();
                closeConsole();
            }
        });

        if (elements.messageInput) {
            elements.messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }
    }

    function toggleConsole() {
        if (consoleOpen) {
            closeConsole();
        } else {
            openConsole();
        }
    }

    function openConsole() {
        if (!elements.console) return;

        elements.console.classList.add('open');
        consoleOpen = true;

        if (isFirstOpen && !hasConnectedBefore) {
            isFirstOpen = false;
            connectToIRC();
        }

        setTimeout(function() {
            if (elements.messageInput) {
                elements.messageInput.focus();
            }
        }, 300);
    }

    function closeConsole() {
        if (!elements.console) return;

        elements.console.classList.remove('open');
        consoleOpen = false;
    }

    function sendMessage() {
        if (!elements.messageInput) return;

        const message = elements.messageInput.value.trim();
        if (!message) return;

        if (message.startsWith('/nick ')) {
            const newNick = message.substring(6).trim();
            if (newNick) {
                changeNickname(newNick);
            } else {
                addSystemMessage('Usage: /nick <new_nickname>');
            }
            elements.messageInput.value = '';
            return;
        }

        if (message.startsWith('/help')) {
            addSystemMessage('Available commands:');
            addSystemMessage('/nick <name> - Change nickname');
            addSystemMessage('/help - Show this help');
            elements.messageInput.value = '';
            return;
        }

        if (!isConnected) {
            addSystemMessage('Not connected to IRC. Reconnecting...');
            connectToIRC();
            return;
        }

        sendIRCCommand('PRIVMSG ' + IRC_CHANNEL + ' :' + message);
        addUserMessage(nickname, message, true);
        elements.messageInput.value = '';
    }

    function changeNickname(newNick) {
        if (!newNick || newNick === nickname) return;

        nickname = newNick;
        updateNicknameDisplay(newNick);
        saveNickname(newNick);

        if (isConnected) {
            sendIRCCommand('NICK ' + newNick);
            addSystemMessage('Attempting to change nickname to ' + newNick + '...');
        } else {
            addSystemMessage('Nickname changed to ' + newNick + ' (will apply on connect)');
        }
    }

    function addSystemMessage(text) {
        addMessage('system', null, text, false, false);
    }

    function addSeparatorLine() {
        if (!elements.chatOutput) return;

        const separator = document.createElement('div');
        separator.className = 'chat-separator';
        separator.innerHTML = '<span>New messages</span>';
        elements.chatOutput.appendChild(separator);
        elements.chatOutput.scrollTop = elements.chatOutput.scrollHeight;
    }

    function flushMessageBuffer() {
        if (messageBuffer.length === 0) return;
        if (!elements.chatOutput) return;

        const fragment = document.createDocumentFragment();
        messageBuffer.forEach(function(messageEl) {
            fragment.appendChild(messageEl);
        });

        elements.chatOutput.appendChild(fragment);
        elements.chatOutput.scrollTop = elements.chatOutput.scrollHeight;
        messageBuffer = [];
    }

    function onHistoryEnd() {
        flushMessageBuffer();
        if (messageCountBeforeSeparator > 0) {
            addSeparatorLine();
        }
        waitingForSeparator = false;
        messageCountBeforeSeparator = 0;
        hasConnectedBefore = true;
        historyDebounceTimeout = null;
    }

    function addUserMessage(user, text, isOwn) {
        const hasMention = !isOwn && text.toLowerCase().includes(nickname.toLowerCase());
        addMessage('user', user, text, isOwn || false, hasMention);

        if (waitingForSeparator) {
            messageCountBeforeSeparator++;

            if (historyDebounceTimeout) {
                clearTimeout(historyDebounceTimeout);
            }
            historyDebounceTimeout = setTimeout(onHistoryEnd, HISTORY_DEBOUNCE_DELAY);
        }
    }

    function createMessageElement(type, user, text, isOwn, hasMention) {
        const messageEl = document.createElement('div');
        messageEl.className = 'chat-message chat-message-' + type;

        if (isOwn) {
            messageEl.classList.add('chat-message-own');
        } else if (hasMention) {
            messageEl.classList.add('chat-message-mention');
        }

        const timestamp = getTimestamp();
        const timestampEl = document.createElement('span');
        timestampEl.className = 'chat-timestamp';
        timestampEl.textContent = timestamp;

        messageEl.appendChild(timestampEl);

        if (type === 'user') {
            const userEl = document.createElement('span');
            userEl.className = isOwn ? 'chat-user-own' : 'chat-user';
            userEl.textContent = '<' + user + '>';
            messageEl.appendChild(userEl);
        }

        const textEl = document.createElement('span');
        textEl.className = 'chat-text';

        if (hasMention) {
            const textFragment = document.createDocumentFragment();
            const escapedNickname = escapeRegExp(nickname);
            const parts = text.split(new RegExp('(' + escapedNickname + ')', 'gi'));
            parts.forEach(function(part) {
                if (part.toLowerCase() === nickname.toLowerCase()) {
                    const mentionSpan = document.createElement('span');
                    mentionSpan.className = 'chat-text-mention';
                    mentionSpan.textContent = part;
                    textFragment.appendChild(mentionSpan);
                } else {
                    textFragment.appendChild(document.createTextNode(part));
                }
            });
            textEl.appendChild(textFragment);
        } else {
            textEl.textContent = text;
        }

        messageEl.appendChild(document.createTextNode(' '));
        messageEl.appendChild(textEl);

        return messageEl;
    }

    function addMessage(type, user, text, isOwn, hasMention) {
        if (!elements.chatOutput) return;

        const messageEl = createMessageElement(type, user, text, isOwn, hasMention);

        if (waitingForSeparator) {
            messageBuffer.push(messageEl);

            if (flushTimeout) {
                clearTimeout(flushTimeout);
            }
            flushTimeout = setTimeout(flushMessageBuffer, 50);
        } else {
            if (messageBuffer.length > 0) {
                flushMessageBuffer();
            }
            elements.chatOutput.appendChild(messageEl);
            elements.chatOutput.scrollTop = elements.chatOutput.scrollHeight;
        }
    }

    function getTimestamp() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        return '[' + hours + ':' + minutes + ':' + seconds + ']';
    }

    function getUserFromPrefix(prefix) {
        return prefix ? prefix.split('!')[0] : 'Unknown';
    }

    function updateNicknameDisplay(newNick) {
        if (elements.nicknameDisplay) {
            elements.nicknameDisplay.textContent = newNick;
        }
    }

    function saveNickname(nick) {
        localStorage.setItem('irc_nickname', nick);
    }

    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function connectToIRC() {
        if (ws && (ws.readyState === WebSocket.CONNECTING || ws.readyState === WebSocket.OPEN)) {
            return;
        }

        addSystemMessage('Connecting...');

        try {
            ws = new WebSocket(IRC_SERVER);

            ws.onopen = function() {
                sendIRCCommand('NICK ' + nickname);
                sendIRCCommand('USER ' + nickname + ' 0 * :' + nickname);
            };

            ws.onmessage = function(event) {
                handleIRCMessage(event.data);
            };

            ws.onerror = function(error) {
                console.error('WebSocket error:', error);
                addSystemMessage('Connection error. Check console for details.');
                isConnected = false;
            };

            ws.onclose = function(event) {
                addSystemMessage('Disconnected (code: ' + event.code + ')');
                isConnected = false;

                if (reconnectAttempts < MAX_RECONNECT_ATTEMPTS) {
                    reconnectAttempts++;
                    const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000);
                    addSystemMessage('Reconnecting in ' + (delay / 1000) + ' seconds... (attempt ' + reconnectAttempts + '/' + MAX_RECONNECT_ATTEMPTS + ')');

                    reconnectTimeout = setTimeout(function() {
                        connectToIRC();
                    }, delay);
                } else {
                    addSystemMessage('Max reconnection attempts reached. Please reload the page.');
                }
            };
        } catch (error) {
            console.error('Failed to create WebSocket:', error);
            addSystemMessage('Failed to connect: ' + error.message);
        }
    }

    function sendIRCCommand(command) {
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            console.warn('Cannot send command, WebSocket not open:', command);
            return;
        }
        ws.send(command + '\r\n');
    }

    function handleIRCMessage(data) {
        const lines = data.split('\r\n');

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;

            const parsed = parseIRCMessage(line);
            if (!parsed) {
                console.warn('Failed to parse line:', line);
                continue;
            }

            if (parsed.command === 'PING') {
                sendIRCCommand('PONG :' + parsed.params[0]);
            } else if (parsed.command === '001') {
                addSystemMessage('Successfully authenticated as ' + nickname);
                sendIRCCommand('JOIN ' + IRC_CHANNEL);
                isConnected = true;
                reconnectAttempts = 0;
            } else if (parsed.command === 'JOIN') {
                const user = getUserFromPrefix(parsed.prefix);
                if (user === nickname) {
                    addSystemMessage('Joined ' + IRC_CHANNEL);
                    waitingForSeparator = true;
                    messageCountBeforeSeparator = 0;
                } else {
                    addSystemMessage(user + ' joined the channel');
                }
            } else if (parsed.command === 'PART') {
                const user = getUserFromPrefix(parsed.prefix);
                addSystemMessage(user + ' left the channel');
            } else if (parsed.command === 'QUIT') {
                const user = getUserFromPrefix(parsed.prefix);
                const reason = parsed.params[0] || 'No reason';
                addSystemMessage(user + ' quit (' + reason + ')');
            } else if (parsed.command === 'PRIVMSG') {
                const user = getUserFromPrefix(parsed.prefix);
                const channel = parsed.params[0];
                const message = parsed.params[1];

                if (channel === IRC_CHANNEL) {
                    const isOwnMessage = user === nickname;
                    addUserMessage(user, message, isOwnMessage);
                }
            } else if (parsed.command === 'NICK') {
                const oldNick = getUserFromPrefix(parsed.prefix);
                const newNick = parsed.params[0];
                addSystemMessage(oldNick + ' is now known as ' + newNick);

                if (oldNick === nickname) {
                    nickname = newNick;
                    updateNicknameDisplay(newNick);
                    saveNickname(newNick);
                }
            } else if (parsed.command === '332') {
                const topic = parsed.params[2];
                addSystemMessage('Topic: ' + topic);
            } else if (parsed.command === '353') {
                const users = parsed.params[3].split(' ');
                addSystemMessage('Users in channel: ' + users.join(', '));
            } else if (parsed.command === '366') {
                // End of names list
            } else if (parsed.command === 'ERROR') {
                addSystemMessage('Server error: ' + parsed.params[0]);
            } else if (parsed.command === '433') {
                addSystemMessage('Nickname already in use, trying another...');
                const newNick = generateDefaultNickname();
                nickname = newNick;
                saveNickname(newNick);
                sendIRCCommand('NICK ' + newNick);
            }
        }
    }

    function parseIRCMessage(line) {
        if (!line) return null;

        const message = {
            raw: line,
            prefix: null,
            command: null,
            params: []
        };

        let position = 0;

        if (line[0] === ':') {
            const spaceIndex = line.indexOf(' ');
            if (spaceIndex === -1) return null;

            message.prefix = line.substring(1, spaceIndex);
            position = spaceIndex + 1;
        }

        let nextSpace = line.indexOf(' ', position);
        if (nextSpace === -1) {
            message.command = line.substring(position);
            return message;
        }

        message.command = line.substring(position, nextSpace);
        position = nextSpace + 1;

        while (position < line.length) {
            if (line[position] === ':') {
                message.params.push(line.substring(position + 1));
                break;
            }

            nextSpace = line.indexOf(' ', position);
            if (nextSpace === -1) {
                message.params.push(line.substring(position));
                break;
            }

            message.params.push(line.substring(position, nextSpace));
            position = nextSpace + 1;
        }

        return message;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initConsole);
    } else {
        initConsole();
    }

    window.QuakeConsole = {
        open: openConsole,
        close: closeConsole,
        toggle: toggleConsole,
        addMessage: addUserMessage,
        addSystemMessage: addSystemMessage,
        connect: connectToIRC,
        isConnected: function() { return isConnected; },
        getNickname: function() { return nickname; },
        setNickname: changeNickname
    };
})();
