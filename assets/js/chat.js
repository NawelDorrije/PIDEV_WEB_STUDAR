import '../css/chat.css';// Ensure functions are globally accessible
window.initializeWebSocket = initializeWebSocket;
window.sendMessage = sendMessage;

let socket;

function initializeWebSocket(userCin) {
    console.log('Initializing WebSocket for user:', userCin);
    
    try {
        socket = new WebSocket('ws://localhost/:8080');
        
        socket.onopen = function() {
            console.log('WebSocket connected');
            socket.send(JSON.stringify({ type: 'register', cin: userCin }));
        };

        socket.onmessage = function(event) {
            try {
                const message = JSON.parse(event.data);
                console.log('Received:', message);
                
                // Skip if message lacks required fields
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
                
                // Append if message involves current user
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

        socket.onclose = function() {
            console.log('WebSocket closed');
        };

        socket.onerror = function(error) {
            console.error('WebSocket error:', error);
        };
    } catch (e) {
        console.error('WebSocket initialization failed:', e);
    }
}

function sendMessage(senderCin, receiverCin) {
    try {
        const input = document.getElementById('comment');
        if (!input) {
            console.error('Input element not found');
            return;
        }
        const content = input.value.trim();
        if (!content) {
            console.log('Empty message ignored');
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
            // Fallback: append locally if server doesn't broadcast
            appendMessage({
                senderCin: senderCin,
                receiverCin: receiverCin,
                content: content,
                timestamp: messageData.timestamp
            });
        } else {
            console.error('WebSocket not connected');
        }
    } catch (e) {
        console.error('Error sending message:', e);
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