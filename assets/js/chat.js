// assets/js/chat.js

// WebSocket and chat functionality
let socket;
let reconnectAttempts = 0;
const maxReconnectAttempts = 5;
const reconnectInterval = 3000;

function initializeWebSocket(userCin) {
    console.log('Initializing WebSocket for user:', userCin);

    if (!userCin) {
        console.error('User CIN is undefined');
        updateSendButtonState(false);
        return;
    }

    try {
        socket = new WebSocket('ws://localhost:8000');

        socket.onopen = function() {
            console.log('WebSocket connected');
            reconnectAttempts = 0;
            socket.send(JSON.stringify({ type: 'register', cin: userCin }));
            updateSendButtonState(true);
        };

        socket.onmessage = function(event) {
            try {
                const message = JSON.parse(event.data);
                console.log('Received:', message);

                if (!message.senderCin || !message.receiverCin || !message.content) {
                    console.log('Invalid message format:', message);
                    return;
                }

                const chatContainer = document.querySelector('.chat-container');
                if (!chatContainer) {
                    console.error('Chat container not found');
                    return;
                }
                const currentUserCin = chatContainer.dataset.currentUserCin || '';
                const receiverCin = chatContainer.dataset.receiverCin || '';

                if (currentUserCin && receiverCin &&
                    (message.senderCin === currentUserCin || message.receiverCin === currentUserCin) &&
                    (message.senderCin === receiverCin || message.receiverCin === receiverCin)) {
                    appendMessage(message);
                } else {
                    console.log('Message ignored (not for this conversation):', message);
                }
            } catch (e) {
                console.error('Error parsing message:', e);
            }
        };

        socket.onclose = function(event) {
            console.log('WebSocket closed:', event);
            updateSendButtonState(false);
            if (reconnectAttempts < maxReconnectAttempts) {
                console.log(`Attempting to reconnect (${reconnectAttempts + 1}/${maxReconnectAttempts})...`);
                setTimeout(() => {
                    reconnectAttempts++;
                    initializeWebSocket(userCin);
                }, reconnectInterval);
            } else {
                console.error('Max reconnect attempts reached. Please refresh the page.');
                alert('Unable to connect to the chat server. Please try again later.');
            }
        };

        socket.onerror = function(error) {
            console.error('WebSocket error:', error);
            updateSendButtonState(false);
        };
    } catch (e) {
        console.error('WebSocket initialization failed:', e);
        updateSendButtonState(false);
    }
}

function sendMessage(senderCin, receiverCin) {
    try {
        const input = document.getElementById('comment');
        if (!input) {
            console.error('Input element not found');
            alert('Chat input field not found. Please refresh the page.');
            return;
        }

        const content = input.value.trim();
        if (!content) {
            console.log('Empty message ignored');
            return;
        }

        if (!senderCin || !receiverCin) {
            console.error('Sender or receiver CIN is missing');
            alert('Unable to send message: User information missing.');
            return;
        }

        const messageData = {
            senderCin: senderCin,
            receiverCin: receiverCin,
            content: content,
            timestamp: new Date().toISOString()
        };

        if (socket && socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify(messageData));
            input.value = '';
            console.log('Sent:', messageData);
            appendMessage({
                senderCin: senderCin,
                receiverCin: receiverCin,
                content: content,
                timestamp: messageData.timestamp
            });
        } else {
            console.error('WebSocket not connected');
            alert('Cannot send message: Chat server is not connected. Please try again later.');
        }
    } catch (e) {
        console.error('Error sending message:', e);
        alert('An error occurred while sending the message. Please try again.');
    }
}

function appendMessage(message) {
    try {
        const conversation = document.getElementById('conversation');
        if (!conversation) {
            console.error('Conversation element not found');
            return;
        }

        const chatContainer = document.querySelector('.chat-container');
        if (!chatContainer) {
            console.error('Chat container not found');
            return;
        }
        const currentUserCin = chatContainer.dataset.currentUserCin || '';
        const isSender = message.senderCin === currentUserCin;
        const messageClass = isSender ? 'message-main-sender' : 'message-main-receiver';
        const bubbleClass = isSender ? 'sender' : 'receiver';

        const timestamp = new Date(message.timestamp).toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        const messageHtml = `
            <div class="message-body">
                <div class="${messageClass}">
                    <div class="${bubbleClass}">
                        <div class="message-text">${message.content}</div>
                        <span class="message-time">${timestamp}</span>
                    </div>
                </div>
            </div>
        `;

        conversation.insertAdjacentHTML('beforeend', messageHtml);
        conversation.scrollTop = conversation.scrollHeight;
        console.log('Appended:', message.content);
    } catch (e) {
        console.error('Error appending message:', e);
    }
}

function updateSendButtonState(isConnected) {
    const sendButton = document.querySelector('.reply-send i');
    if (sendButton) {
        if (isConnected) {
            sendButton.classList.remove('text-muted');
            sendButton.style.cursor = 'pointer';
            sendButton.setAttribute('aria-disabled', 'false');
        } else {
            sendButton.classList.add('text-muted');
            sendButton.style.cursor = 'not-allowed';
            sendButton.setAttribute('aria-disabled', 'true');
        }
    }

    const textarea = document.getElementById('comment');
    if (textarea) {
        textarea.disabled = !isConnected;
    }
}

// Expose functions to global scope
window.initializeWebSocket = initializeWebSocket;
window.sendMessage = sendMessage;
window.appendMessage = appendMessage;
window.updateSendButtonState = updateSendButtonState;