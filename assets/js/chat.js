// assets/js/chat.js
// assets/js/chat.js
import $ from 'jquery';
import 'bootstrap';
import 'font-awesome/css/font-awesome.min.css';
import '../css/chat.css'; // Import the chat styles

window.jQuery = window.$ = $;
// assets/js/chat.js
$(document).ready(function () {
    // Scroll to the bottom of the message area on page load
    const messageContainer = document.querySelector('.message');
    if (messageContainer) {
        messageContainer.scrollTop = messageContainer.scrollHeight;
    }

    // Enable/disable the textarea based on whether a conversation is selected
    const headingName = $('.heading-name-meta').text().trim();
    const textarea = $('#comment');
    const sendButton = $('.reply-send i');
    if (headingName === 'Select a conversation') {
        textarea.prop('disabled', true);
        sendButton.css('cursor', 'not-allowed');
    } else {
        textarea.prop('disabled', false);
        sendButton.css('cursor', 'pointer');
    }
});

// Function to load previous messages
window.loadPreviousMessages = function (receiverCin, offset, limit) {
    $.ajax({
        url: `/chat/${receiverCin}/load-previous?offset=${offset}&limit=${limit}`,
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        },
        success: function (data) {
            const conversation = $('#conversation');
            const previousDiv = $('.message-previous');
            let previousDate = null;

            // Store the current scroll position to maintain it after adding messages
            const scrollHeightBefore = conversation[0].scrollHeight;
            const scrollTopBefore = conversation[0].scrollTop;

            // Add the previous messages
            data.messages.forEach(function (message) {
                const currentDate = message.timestamp.split(' ')[0];
                if (previousDate && currentDate !== previousDate) {
                    const dateSeparator = `
                        <div class="message-date-separator">
                            <div class="text-center">
                                <span class="date-label">${currentDate}</span>
                            </div>
                        </div>
                    `;
                    previousDiv.after(dateSeparator);
                }

                const messageHtml = `
                    <div class="message-body">
                        <div class="${message.isSender ? 'message-main-sender' : 'message-main-receiver'}">
                            <div class="${message.isSender ? 'sender' : 'receiver'}">
                                <div class="message-text">${message.content}</div>
                                <span class="message-time">${message.timestamp}</span>
                            </div>
                        </div>
                    </div>
                `;
                previousDiv.after(messageHtml);

                previousDate = currentDate;
            });

            // Adjust the scroll position to maintain the view
            const scrollHeightAfter = conversation[0].scrollHeight;
            conversation[0].scrollTop = scrollTopBefore + (scrollHeightAfter - scrollHeightBefore);

            // Update the "Show Previous Message!" link
            const previousLink = $('#load-previous');
            if (data.new_offset > 0) {
                previousLink.attr('onclick', `loadPreviousMessages('${receiverCin}', '${data.new_offset}', '${limit}')`);
                previousLink.attr('data-offset', data.new_offset);
            } else {
                previousLink.parent().remove();
            }
        },
        error: function (xhr, status, error) {
            console.error('Erreur:', error);
        }
    });
};

// Function to send a new message
window.sendMessage = function (receiverCin, url) {
    const comment = $('#comment').val().trim();
    if (comment === '') return;

    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/x-www-form-urlencoded',
        data: {
            receiverCin: receiverCin,
            content: comment,
        },
        success: function (data) {
            if (data.success) {
                const conversation = $('#conversation');
                const messageHtml = `
                    <div class="message-body">
                        <div class="message-main-sender">
                            <div class="sender">
                                <div class="message-text">${data.message.content}</div>
                                <span class="message-time">${data.message.timestamp}</span>
                            </div>
                        </div>
                    </div>
                `;
                conversation.append(messageHtml);

                // Scroll to the bottom after adding the new message
                conversation[0].scrollTop = conversation[0].scrollHeight;
                $('#comment').val('');
            } else {
                alert('Erreur lors de l\'envoi du message');
            }
        },
        error: function (xhr, status, error) {
            console.error('Erreur:', error);
        }
    });
};