// assets/js/chat.js
// assets/js/chat.js
import $ from 'jquery';
import 'bootstrap';
import 'font-awesome/css/font-awesome.min.css';
import '../css/chat.css'; // Import the chat styles

window.jQuery = window.$ = $;
function sendMessage(receiverCin, sendUrl) {
    const messageInput = document.getElementById('comment');
    const messageContent = messageInput.value.trim();

    if (messageContent === '') {
        return; // Don't send empty messages
    }

    // Send the message via AJAX
    fetch(sendUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            receiverCin: receiverCin,
            content: messageContent
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add the new message to the conversation
            const conversation = document.getElementById('conversation');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'row message-body';
            messageDiv.innerHTML = `
                <div class="col-sm-12 message-main-sender">
                    <div class="sender">
                        <div class="message-text">${messageContent}</div>
                        <span class="message-time">${new Date().toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                    </div>
                </div>
            `;
            conversation.appendChild(messageDiv);
            messageInput.value = ''; // Clear the input
            conversation.scrollTop = conversation.scrollHeight; // Scroll to the bottom
        } else {
            console.error('Failed to send message:', data.error);
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
    });
}

function previous(element) {
    const conversationId = element.id;
    const limit = element.getAttribute('name');
    console.log(`Loading previous messages for conversation ${conversationId} with limit ${limit}`);
    // Add logic to load previous messages via AJAX if needed
}