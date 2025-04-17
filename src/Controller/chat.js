import '../css/chat.css';
let ws = null;

function initializeWebSocket(currentUserCin) {
    if (ws && ws.readyState !== WebSocket.CLOSED) {
        return;
    }

    ws = new WebSocket('ws://localhost:8080');
    ws.onopen = function() {
        console.log('Connected to WebSocket server');
        ws.send(JSON.stringify({
            type: 'register',
            cin: currentUserCin
        }));
    };

    ws.onmessage = function(event) {
        const data = JSON.parse(event.data);
        const conversation = document.getElementById('conversation');
        if (!conversation) return;

        const currentUserCin = document.querySelector('body').dataset.currentUserCin || currentUserCin;
        const receiverCin = document.querySelector('body').dataset.receiverCin;

        if ((data.senderCin === currentUserCin && data.receiverCin === receiverCin) ||
            (data.senderCin === receiverCin && data.receiverCin === currentUserCin)) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message-body';
            const isSender = data.senderCin === currentUserCin;
            const messageMainClass = isSender ? 'message-main-sender' : 'message-main-receiver';
            const messageSubClass = isSender ? 'sender' : 'receiver';

            messageDiv.innerHTML = `
                <div class="${messageMainClass}">
                    <div class="${messageSubClass}">
                        <div class="message-text">${data.content}</div>
                        <span class="message-time">${data.timestamp}</span>
                    </div>
                </div>
            `;
            conversation.appendChild(messageDiv);
            conversation.scrollTop = conversation.scrollHeight;
        }
    };

    ws.onerror = function(error) {
        console.error('WebSocket error:', error);
        alert('Failed to connect to WebSocket server. Please try again later.');
    };

    ws.onclose = function() {
        console.log('WebSocket connection closed');
        alert('WebSocket connection closed. Please refresh the page to reconnect.');
    };
}

window.sendMessage = function(currentUserCin, receiverCin, sendUrl) {
    const comment = document.getElementById('comment');
    const content = comment.value.trim();

    if (!content) {
        alert('Message cannot be empty');
        return;
    }

    fetch(sendUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            receiverCin: receiverCin,
            content: content
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    senderCin: currentUserCin,
                    receiverCin: receiverCin,
                    content: data.message.content,
                    timestamp: data.message.timestamp
                }));
            } else {
                const conversation = document.getElementById('conversation');
                const messageDiv = document.createElement('div');
                messageDiv.className = 'message-body';
                messageDiv.innerHTML = `
                    <div class="message-main-sender">
                        <div class="sender">
                            <div class="message-text">${data.message.content}</div>
                            <span class="message-time">${data.message.timestamp}</span>
                        </div>
                    </div>
                `;
                conversation.appendChild(messageDiv);
                conversation.scrollTop = conversation.scrollHeight;
                console.warn('WebSocket is not open. Message appended manually.');
            }
            comment.value = '';
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        alert('Failed to send message: ' + error.message);
    });
};

window.loadPreviousMessages = function(receiverCin, offset, limit) {
    fetch(`/chat/${receiverCin}/load-previous?offset=${offset}&limit=${limit}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        const conversation = document.getElementById('conversation');
        const previousMessagesDiv = document.getElementById('previous-messages');

        data.messages.forEach(message => {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message-body';
            const isSender = message.isSender;
            const messageMainClass = isSender ? 'message-main-sender' : 'message-main-receiver';
            const messageSubClass = isSender ? 'sender' : 'receiver';

            messageDiv.innerHTML = `
                <div class="${messageMainClass}">
                    <div class="${messageSubClass}">
                        <div class="message-text">${message.content}</div>
                        <span class="message-time">${message.timestamp}</span>
                    </div>
                </div>
            `;
            conversation.insertBefore(messageDiv, conversation.firstChild.nextSibling);
        });

        if (data.new_offset > 0) {
            previousMessagesDiv.innerHTML = `
                <div class="previous">
                    <a id="load-previous" onclick="loadPreviousMessages('${receiverCin}', '${data.new_offset}', '${limit}')" data-offset="${data.new_offset}">
                        Show Previous Message!
                    </a>
                </div>
            `;
        } else {
            previousMessagesDiv.innerHTML = '';
        }
    })
    .catch(error => {
        console.error('Error loading previous messages:', error);
        alert('Failed to load previous messages');
    });
};