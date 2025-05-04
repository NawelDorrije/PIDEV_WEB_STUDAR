import '../css/chat.css';
let ws = null;

window.initializeWebSocket = function(currentUserCin, receiverCin) {
    console.log('Initializing WebSocket for:', currentUserCin, receiverCin);
    ws = new WebSocket('ws://127.0.0.1:8000');

    ws.onopen = function() {
        console.log('WebSocket connection established');
    };

    ws.onerror = function(error) {
        console.error('WebSocket error:', error);
    };

    ws.onclose = function() {
        console.log('WebSocket connection closed');
        ws = null;
    };

    ws.onmessage = function(event) {
        const data = JSON.parse(event.data);
        const conversation = document.getElementById('conversation');
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message-body';
        const isSender = data.senderCin === currentUserCin;
        messageDiv.innerHTML = `
            <div class="${isSender ? 'message-main-sender' : 'message-main-receiver'}">
                <div class="${isSender ? 'sender' : 'receiver'}">
                    <div class="message-text">${data.content}</div>
                    <span class="message-time">${data.timestamp}</span>
                </div>
            </div>
        `;
        conversation.appendChild(messageDiv);
        conversation.scrollTop = conversation.scrollHeight;
    };
};

window.sendMessage = function(currentUserCin, receiverCin) {
    console.log('sendMessage called:', currentUserCin, receiverCin);
    const comment = document.getElementById('comment');
    const content = comment.value.trim();

    if (!content) {
        alert('Message cannot be empty');
        return;
    }

    if (ws && ws.readyState === WebSocket.OPEN) {
        const timestamp = new Date().toISOString();
        ws.send(JSON.stringify({
            senderCin: currentUserCin,
            receiverCin: receiverCin,
            content: content,
            timestamp: timestamp
        }));
        comment.value = '';
    } else {
        alert('WebSocket connection is not open. Please try again later.');
    }
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