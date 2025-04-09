// assets/js/chat.js
import $ from 'jquery';

export function sendMessage(receiverCin, sendPath) {
    const content = document.getElementById('comment').value;
    if (!content) {
        alert('Veuillez entrer un message.');
        return;
    }

    $.ajax({
        url: sendPath,
        method: 'POST',
        data: {
            receiverCin: receiverCin,
            content: content
        },
        success: function(response) {
            // Clear the textarea
            document.getElementById('comment').value = '';

            // Create the new message HTML
            const timestamp = new Date().toLocaleString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            const messageHtml = `
                <div class="row message-body">
                    <div class="col-sm-12 message-main-sender">
                        <div class="sender">
                            <div class="message-text">
                                ${content}
                            </div>
                            <span class="message-time pull-right">
                                ${timestamp}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            // Append the new message to the conversation
            document.getElementById('conversation').insertAdjacentHTML('beforeend', messageHtml);

            // Scroll to the bottom of the conversation
            const conversationDiv = document.getElementById('conversation');
            conversationDiv.scrollTop = conversationDiv.scrollHeight;
        },
        error: function(xhr) {
            alert('Erreur lors de l\'envoi du message : ' + (xhr.responseJSON?.error || 'Erreur inconnue'));
        }
    });
}

$(document).ready(function() {
    $(".heading-compose").click(function() {
        $(".side-two").css({
            "left": "0"
        });
    });

    $(".newMessage-back").click(function() {
        $(".side-two").css({
            "left": "-100%"
        });
    });
});