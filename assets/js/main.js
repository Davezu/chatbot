/**
 * Bus Rental Chat Service - Main JavaScript
 * Contains all client-side functionality for chatbot and admin interfaces
 */

document.addEventListener('DOMContentLoaded', function() {
    // Scroll chat to bottom on load
    scrollChatToBottom();
    
    // Set up form handlers
    setupFormHandlers();
    
    // Set up auto-refresh for waiting status
    setupAutoRefresh();
    
    // Set up quick reply buttons in admin chat
    setupQuickReplyButtons();
});

/**
 * Scroll the chat messages container to the bottom
 */
function scrollChatToBottom() {
    var chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

/**
 * Set up form submission handlers
 */
function setupFormHandlers() {
    const form = document.querySelector('.chat-input form');
    if (form) {
        form.addEventListener('submit', function(event) {
            // Check if we're in assistance mode
            if (this.getAttribute('data-assistance-mode') === 'true') {
                event.preventDefault();
                
                const message = this.querySelector('textarea').value.trim();
                
                if (message) {
                    // Redirect to connect_to_admin with the problem description
                    window.location.href = "index.php?connect_to_admin=1&problem=" + encodeURIComponent(message);
                } else {
                    // If empty message, show an error
                    alert("Please provide a brief description to help our agents assist you better.");
                }
            }
            // Otherwise, let the form submit normally
        });
    }
}

/**
 * Set up auto-refresh for waiting for admin response
 */
function setupAutoRefresh() {
    // Check if there's a human-requested status element
    var statusElement = document.querySelector('.status-human-requested');
    if (statusElement) {
        // Set a refresh interval when waiting for admin (every 15 seconds)
        var refreshInterval = setInterval(function() {
            // Show loading indicator
            var spinnerElement = statusElement.querySelector('.spinner-border');
            if (spinnerElement) {
                spinnerElement.style.opacity = '1';
            }
            
            // Reload the page after a short delay to show the spinner
            setTimeout(function() {
                window.location.reload();
            }, 500);
        }, 15000); // 15 seconds
    }
}

/**
 * Function to handle assistance request
 */
function confirmAssistance(event) {
    event.preventDefault();
    
    // Show a message in the chat asking for assistance details
    const chatMessages = document.getElementById('chatMessages');
    const botMessage = document.createElement('div');
    botMessage.className = 'message bot-message';
    botMessage.innerHTML = `
        <div class="message-content">
            <div><small class="text-muted">Bus Rental Bot</small></div>
            <p>Please describe what you need help with in the text box below and click send. This will connect you with a customer service representative.</p>
        </div>
    `;
    chatMessages.appendChild(botMessage);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    // Modify the form to submit to connect_to_admin instead
    const form = document.querySelector('.chat-input form');
    form.setAttribute('data-assistance-mode', 'true');
    
    // Change the UI to indicate assistance mode
    const chatInput = document.querySelector('.chat-input');
    chatInput.classList.add('assistance-mode');
    
    // Change the placeholder text and ensure it's not disabled
    const textarea = form.querySelector('textarea');
    textarea.placeholder = "Describe what you need help with...";
    textarea.disabled = false;
    textarea.focus();
    
    // Change the button color and ensure it's not disabled
    const button = form.querySelector('button');
    button.classList.remove('btn-primary');
    button.classList.add('btn-success');
    button.disabled = false;
    
    // Add an indicator that we're in assistance request mode
    const indicator = document.createElement('div');
    indicator.className = 'assistance-indicator';
    indicator.innerHTML = '<i class="fas fa-headset me-1"></i> Requesting assistance';
    form.appendChild(indicator);
}

/**
 * Set up quick reply buttons in admin chat
 */
function setupQuickReplyButtons() {
    // Set up quick reply buttons in admin chat
    var quickReplyButtons = document.querySelectorAll('.quick-reply-btn');
    if (quickReplyButtons.length > 0) {
        quickReplyButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var messageInput = document.getElementById('messageInput');
                if (messageInput) {
                    messageInput.value = this.getAttribute('data-reply');
                    messageInput.focus();
                }
            });
        });
    }
} 