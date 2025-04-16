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
    
    // Set up real-time chat functionality
    setupRealTimeChat();
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
            // Always prevent default form submission for real-time chat
            event.preventDefault();
            
            // Check if we're in assistance mode
            if (this.getAttribute('data-assistance-mode') === 'true') {
                const message = this.querySelector('textarea').value.trim();
                
                if (message) {
                    // Use Ajax to request human assistance
                    requestHumanAssistance(message);
                } else {
                    // If empty message, show an error
                    alert("Please provide a brief description to help our agents assist you better.");
                }
            } else {
                // Handle normal message submission
                const message = this.querySelector('textarea').value.trim();
                if (message) {
                    sendChatMessage(message);
                    // Clear the textarea
                    this.querySelector('textarea').value = '';
                }
            }
        });
    }
    
    // Set up quick question buttons
    const quickButtons = document.querySelectorAll('.quick-btn');
    if (quickButtons.length) {
        quickButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                const question = this.getAttribute('href').split('=')[1];
                if (question) {
                    sendQuickQuestion(decodeURIComponent(question));
                }
            });
        });
    }
}

/**
 * Send a chat message via Ajax
 */
function sendChatMessage(message) {
    // Generate a temporary ID for this message
    const tempId = 'temp-' + Date.now();
    
    // Add message to the UI immediately (optimistic UI update)
    addMessageToUI({
        id: tempId,
        content: message,
        sender_type: 'client',
        sender_name: 'You'
    });
    
    // Send message to server
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('message', message);
    
    fetch('includes/ajax_handlers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the temporary message with the real ID
            const tempMessage = document.querySelector(`[data-message-id="${tempId}"]`);
            if (tempMessage) {
                tempMessage.setAttribute('data-message-id', data.message_id);
                tempMessage.classList.remove('temp-message');
            }
            
            // Handle bot responses if any
            if (data.responses && data.responses.length) {
                data.responses.forEach(response => {
                    addMessageToUI(response);
                });
            }
            
            // Update chat status if needed
            updateChatStatus(data.status);
        } else {
            console.error('Error sending message:', data.message);
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
    });
}

/**
 * Add a message to the UI
 */
function addMessageToUI(message) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;
    
    // First check if this message already exists in the DOM
    const existingMessage = document.querySelector(`[data-message-id="${message.id}"]`);
    if (existingMessage) {
        return; // Skip adding if it already exists
    }
    
    const messageDiv = document.createElement('div');
    
    // Add appropriate classes
    messageDiv.className = `message ${message.sender_type}-message`;
    if (message.id && String(message.id).startsWith('temp-')) {
        messageDiv.classList.add('temp-message');
    }
    
    // Set message ID for tracking
    if (message.id) {
        messageDiv.setAttribute('data-message-id', message.id);
    }
    
    let senderNameHTML = '';
    if (message.sender_type === 'client') {
        senderNameHTML = `<div class="message-meta"><span class="sender-name">${message.sender_name}</span></div>`;
    } else {
        senderNameHTML = `<div class="message-meta"><small class="text-muted">${message.sender_name}</small></div>`;
    }
    
    messageDiv.innerHTML = `
        <div class="message-content">
            ${senderNameHTML}
            <div class="message-text">${message.content}</div>
        </div>
    `;
    
    chatMessages.appendChild(messageDiv);
    scrollChatToBottom();
}

/**
 * Update chat status UI based on conversation status
 */
function updateChatStatus(status) {
    const chatInput = document.querySelector('.chat-input');
    const textarea = chatInput.querySelector('textarea');
    const sendButton = chatInput.querySelector('button');
    const statusContainers = document.querySelectorAll('.conversation-status');
    
    // Remove old status containers
    statusContainers.forEach(container => container.remove());
    
    // Handle different statuses
    if (status === 'human_requested') {
        // Show waiting status
        const waitingStatus = document.createElement('div');
        waitingStatus.className = 'conversation-status status-human-requested';
        waitingStatus.innerHTML = `
            <div class="d-flex align-items-center p-3 bg-warning-subtle border border-warning rounded">
                <div class="spinner-border spinner-border-sm text-warning me-2" role="status">
                    <span class="visually-hidden">Connecting...</span>
                </div>
                <div><strong>Connecting to an agent...</strong> Our customer service team has been notified. Please wait while an available agent joins your conversation. This usually takes less than 2 minutes during business hours.</div>
            </div>
        `;
        chatInput.parentNode.insertBefore(waitingStatus, chatInput);
        
        // Disable chat input
        textarea.disabled = true;
        textarea.placeholder = "Waiting for an agent to connect...";
        sendButton.disabled = true;
        
        // Start polling for status changes
        startStatusPolling();
    } else if (status === 'human_assigned') {
        // Show connected status
        const connectedStatus = document.createElement('div');
        connectedStatus.className = 'conversation-status status-human-assigned';
        connectedStatus.innerHTML = `
            <div class="d-flex align-items-center p-3 bg-success-subtle border border-success rounded">
                <div class="text-success me-2"><i class="fas fa-user-check"></i></div>
                <div><strong>Agent connected!</strong> You are now chatting with a customer service representative who will assist you with your inquiry.</div>
            </div>
        `;
        chatInput.parentNode.insertBefore(connectedStatus, chatInput);
        
        // Enable chat input
        textarea.disabled = false;
        textarea.placeholder = "Type your message here...";
        sendButton.disabled = false;
        
        // Hide assistance button if present
        const assistanceBtn = document.querySelector('.btn-assistance');
        if (assistanceBtn) {
            assistanceBtn.style.display = 'none';
        }
    }
}

/**
 * Send a quick question via Ajax
 */
function sendQuickQuestion(question) {
    // Generate temporary IDs for this message
    const clientTempId = 'temp-client-' + Date.now();
    
    // Add client message to UI immediately (optimistic UI update)
    addMessageToUI({
        id: clientTempId,
        content: question,
        sender_type: 'client',
        sender_name: 'You'
    });
    
    const formData = new FormData();
    formData.append('action', 'quick_question');
    formData.append('question', question);
    
    fetch('includes/ajax_handlers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the temporary client message with the real ID
            const tempClientMessage = document.querySelector(`[data-message-id="${clientTempId}"]`);
            if (tempClientMessage) {
                tempClientMessage.setAttribute('data-message-id', data.client_message.id);
                tempClientMessage.classList.remove('temp-message');
            }
            
            // Add bot response to UI if it's not already there
            const existingBotMessage = document.querySelector(`[data-message-id="${data.bot_message.id}"]`);
            if (!existingBotMessage) {
                addMessageToUI(data.bot_message);
            }
            
            // Update chat status if needed
            updateChatStatus(data.status);
        } else {
            console.error('Error sending quick question:', data.message);
        }
    })
    .catch(error => {
        console.error('Error sending quick question:', error);
    });
}

/**
 * Request human assistance via Ajax
 */
function requestHumanAssistance(problemDescription = '') {
    const formData = new FormData();
    formData.append('action', 'request_human');
    
    // Generate temporary ID for client message
    const clientTempId = problemDescription ? 'temp-client-' + Date.now() : null;
    
    if (problemDescription) {
        formData.append('problem', problemDescription);
        
        // Add client message to UI immediately
        addMessageToUI({
            id: clientTempId,
            content: problemDescription,
            sender_type: 'client',
            sender_name: 'You'
        });
    }
    
    fetch('includes/ajax_handlers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update temporary client message with real ID if it exists
            if (clientTempId) {
                const tempClientMessage = document.querySelector(`[data-message-id="${clientTempId}"]`);
                if (tempClientMessage) {
                    // We don't know the exact ID, but we can remove the temp class
                    tempClientMessage.classList.remove('temp-message');
                }
            }
            
            // Check if this bot message already exists
            const existingBotMessage = document.querySelector(`[data-message-id="${data.message.id}"]`);
            if (!existingBotMessage) {
                // Add bot message to UI
                addMessageToUI(data.message);
            }
            
            // Update chat status
            updateChatStatus(data.status);
            
            // Reset any assistance mode UI
            const form = document.querySelector('.chat-input form');
            if (form) {
                form.removeAttribute('data-assistance-mode');
                
                // Remove assistance indicator if it exists
                const indicator = form.querySelector('.assistance-indicator');
                if (indicator) {
                    indicator.remove();
                }
                
                // Reset button styles
                const button = form.querySelector('button');
                if (button) {
                    button.classList.remove('btn-success');
                    button.classList.add('btn-primary');
                }
            }
        } else {
            console.error('Error requesting human assistance:', data.message);
        }
    })
    .catch(error => {
        console.error('Error requesting human assistance:', error);
    });
}

/**
 * Set up automatic refresh for waiting status
 */
function setupAutoRefresh() {
    // This will be handled by the polling mechanism
}

/**
 * Start polling for new messages and status changes
 */
function setupRealTimeChat() {
    // Keep track of the last message ID we've seen
    let lastMessageId = 0;
    
    // Get initial last message ID
    const messages = document.querySelectorAll('.message');
    if (messages.length) {
        // Try to get IDs from data attributes if they exist
        const lastMessage = messages[messages.length - 1];
        if (lastMessage.hasAttribute('data-message-id')) {
            const msgId = lastMessage.getAttribute('data-message-id');
            // Only set lastMessageId if it's not a temporary ID
            if (!msgId.startsWith('temp-')) {
                lastMessageId = parseInt(msgId);
            }
        }
    }
    
    // Poll for new messages every 3 seconds
    const pollInterval = setInterval(pollForNewMessages, 3000);
    
    // Function to poll for new messages
    function pollForNewMessages() {
        const formData = new FormData();
        formData.append('action', 'get_messages');
        formData.append('last_message_id', lastMessageId);
        
        fetch('includes/ajax_handlers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add new messages to UI
                if (data.messages && data.messages.length) {
                    data.messages.forEach(message => {
                        // Check if this message already exists in the DOM
                        const existingMessage = document.querySelector(`[data-message-id="${message.id}"]`);
                        if (!existingMessage) {
                            addMessageToUI(message);
                        }
                        lastMessageId = Math.max(lastMessageId, message.id);
                    });
                }
                
                // Check if conversation status has changed
                const currentStatusEl = document.querySelector('.status-human-assigned, .status-human-requested');
                const currentStatus = currentStatusEl ? 
                    (currentStatusEl.classList.contains('status-human-assigned') ? 'human_assigned' : 'human_requested') : 
                    'bot';
                
                if (currentStatus !== data.status) {
                    updateChatStatus(data.status);
                }
            }
        })
        .catch(error => {
            console.error('Error polling for new messages:', error);
        });
    }
}

/**
 * Set up quick reply buttons in admin chat
 */
function setupQuickReplyButtons() {
    const quickReplyButtons = document.querySelectorAll('.quick-reply-btn');
    const messageInput = document.getElementById('messageInput');
    
    if (quickReplyButtons.length && messageInput) {
        quickReplyButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                messageInput.value = this.getAttribute('data-reply');
                messageInput.focus();
            });
        });
    }
}

/**
 * Function to confirm human assistance request
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
    scrollChatToBottom();
    
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
 * Reset chat after deciding not to connect to human
 */
function resetChat() {
    const chatInput = document.querySelector('.chat-input');
    const textarea = chatInput.querySelector('textarea');
    const button = chatInput.querySelector('button');
    
    // Just re-enable the input
    textarea.disabled = false;
    textarea.placeholder = "Type your message here...";
    button.disabled = false;
    textarea.focus();
} 