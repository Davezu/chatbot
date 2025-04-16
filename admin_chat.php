<?php
// Include required files
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/chatbot.php';

// Initialize session
initSession();

// Auto login as admin (bypassing login requirement)
if (!isLoggedIn() || !isAdmin()) {
    // Set admin session variables manually
    $_SESSION['user_id'] = 1; // Assuming admin user ID is 1
    $_SESSION['username'] = 'admin';
    $_SESSION['email'] = 'admin@example.com';
    $_SESSION['role'] = 'admin';
}

$currentUser = getCurrentUser();

// Check if conversation ID is provided
if (!isset($_GET['id'])) {
    header('Location: admin.php');
    exit;
}

$conversationId = $_GET['id'];

// Get conversation details
$conversation = getRow(
    "SELECT c.*, u.username as client_name 
    FROM conversations c
    JOIN users u ON c.client_id = u.id
    WHERE c.id = ?",
    [$conversationId],
    "i"
);

// If conversation doesn't exist, redirect to admin panel
if (!$conversation) {
    header('Location: admin.php');
    exit;
}

// Check if this admin is assigned to this conversation or if it's closed
if ($conversation['status'] === 'human_assigned' && $conversation['admin_id'] != $currentUser['id'] && $conversation['status'] !== 'closed') {
    header('Location: admin.php');
    exit;
}

// Handle assign admin to conversation
if (isset($_GET['assign']) && $conversation['status'] === 'human_requested') {
    // Assign admin to this conversation
    assignAdminToConversation($conversationId, $currentUser['id']);
    
    // Add system message about admin joining
    addMessage($conversationId, 'bot', "Customer service representative " . htmlspecialchars($currentUser['username']) . " has joined the conversation and will assist you shortly.");
    
    // Redirect to prevent resubmission
    header('Location: admin_chat.php?id=' . $conversationId);
    exit;
}

// Handle message submission
if (isset($_POST['send_message'])) {
    $message = $_POST['message'];
    
    // If this is the first response and the admin hasn't been assigned yet,
    // assign this admin to the conversation
    if ($conversation['status'] === 'human_requested' && $conversation['admin_id'] === null) {
        assignAdminToConversation($conversationId, $currentUser['id']);
        
        // Add system message about admin joining
        addMessage($conversationId, 'bot', "Customer service representative " . htmlspecialchars($currentUser['username']) . " has joined the conversation and will assist you shortly.");
    }
    
    // Add admin message to conversation
    addMessage($conversationId, 'admin', $message);
    
    // Redirect to prevent form resubmission
    header('Location: admin_chat.php?id=' . $conversationId);
    exit;
}

// Handle closing conversation
if (isset($_POST['close_conversation'])) {
    // Get closing message from form
    $closingMessage = isset($_POST['closing_message']) && !empty($_POST['closing_message']) 
        ? $_POST['closing_message'] 
        : "This conversation has been closed by the customer service agent. If you have additional questions, you can start a new conversation.";
    
    // Close the conversation
    closeConversation($conversationId);
    
    // Add system message with custom closing message
    addMessage($conversationId, 'bot', $closingMessage);
    
    // Redirect to admin panel
    header('Location: admin.php');
    exit;
}

// Get all messages for this conversation
$messages = getConversationMessages($conversationId);

// Function to format date for display
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('M j, Y g:i A');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat - Bus Rental Chat Service</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .chat-container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .chat-header {
            background-color: #28a745;
            color: white;
            padding: 15px;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .chat-messages {
            height: 500px;
            overflow-y: auto;
            padding: 15px;
            background-color: #f9f9f9;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-content {
            padding: 10px 15px;
            border-radius: 20px;
            max-width: 75%;
        }
        .bot-message .message-content {
            background-color: #e9ecef;
            color: #212529;
        }
        .admin-message {
            justify-content: flex-end;
        }
        .admin-message .message-content {
            background-color: #28a745;
            color: white;
        }
        .client-message .message-content {
            background-color: #0066cc;
            color: white;
        }
        .chat-input {
            padding: 15px;
            border-top: 1px solid #e9ecef;
        }
        .chat-input textarea {
            resize: none;
            border-radius: 20px;
        }
        .badge-human-assigned {
            background-color: #28a745;
            color: white;
        }
        .badge-closed {
            background-color: #6c757d;
            color: white;
        }
        .quick-replies {
            margin-bottom: 15px;
        }
        
        /* Style for temporary messages */
        .temp-message .message-content {
            opacity: 0.8;
            position: relative;
        }
        
        .temp-message .message-content::after {
            content: '';
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.6);
            animation: pulse 1s infinite;
        }
        
        [data-message-id^="temp-"] .message-content {
            opacity: 0.8;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(0.95);
                opacity: 0.7;
            }
            70% {
                transform: scale(1);
                opacity: 1;
            }
            100% {
                transform: scale(0.95);
                opacity: 0.7;
            }
        }
        
        /* Close conversation modal styling */
        #closeConversationModal .modal-header {
            background-color: #dc3545;
            color: white;
            padding: 10px 15px;
        }
        #closeConversationModal .modal-title {
            font-size: 1.2rem;
            font-weight: 500;
        }
        #closeConversationModal .modal-body {
            padding: 20px;
        }
        #closeConversationModal .form-label {
            font-weight: 500;
        }
        #closeConversationModal .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        #closeConversationModal .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .closing-template {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        /* Polling indicator styles */
        .polling-status {
            display: inline-block;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }
        
        .polling-status.active {
            opacity: 1;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-bus me-2"></i>
                Bus Rental Chat Service
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user-shield me-1"></i>
                            Admin: <?php echo htmlspecialchars($currentUser['username']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">
                            <i class="fas fa-cog me-1"></i>
                            Admin Panel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-comment me-1"></i>
                            Chat Interface
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row mb-4">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Admin Panel</a></li>
                        <li class="breadcrumb-item active">Conversation with <?php echo htmlspecialchars($conversation['client_name']); ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="chat-container">
            <div class="chat-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-comments me-2"></i>
                        Conversation with <?php echo htmlspecialchars($conversation['client_name']); ?>
                    </h4>
                    <div>
                        <span class="badge <?php echo $conversation['status'] === 'closed' ? 'bg-secondary' : 'bg-success'; ?>">
                            <?php 
                            echo $conversation['status'] === 'human_assigned' ? 'Active' : 
                                ($conversation['status'] === 'closed' ? 'Closed' : $conversation['status']); 
                            ?>
                        </span>
                        <span id="pollingIndicator" class="polling-status ms-2" title="Checking for new messages...">
                            <i class="fas fa-sync-alt fa-spin fa-xs text-muted"></i>
                        </span>
                        <button id="refreshButton" type="button" class="btn btn-sm btn-outline-info ms-2" title="Refresh Messages">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <?php if ($conversation['status'] !== 'closed'): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-2" data-bs-toggle="modal" data-bs-target="#closeConversationModal">
                                <i class="fas fa-times me-1"></i> Close
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($conversation['status'] === 'human_requested' && $conversation['admin_id'] === null): ?>
            <!-- Customer waiting notification -->
            <div class="alert alert-warning m-3">
                <div class="d-flex align-items-center">
                    <div class="me-3"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                    <div>
                        <h5 class="alert-heading">Customer waiting for assistance!</h5>
                        <p class="mb-0">This customer has requested to speak with a human agent. Please assign yourself to this conversation by clicking the button below.</p>
                        <a href="admin_chat.php?id=<?php echo $conversationId; ?>&assign=1" class="btn btn-primary mt-2">
                            <i class="fas fa-user-check me-1"></i> Assign me to this conversation
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="chat-messages" id="chatMessages">
                <?php
                foreach ($messages as $message) {
                    $messageClass = $message['sender_type'] . '-message';
                    $senderName = $message['sender_type'] === 'bot' ? 'Bus Rental Bot' : 
                                  ($message['sender_type'] === 'admin' ? 'You (Agent)' : $conversation['client_name']);
                    echo '<div class="message ' . $messageClass . '" data-message-id="' . $message['id'] . '">';
                    echo '<div class="message-content">';
                    echo '<div><small class="text-muted">' . htmlspecialchars($senderName) . ' - ' . formatDate($message['sent_at']) . '</small></div>';
                    echo $message['message'];
                    echo '</div></div>';
                }
                ?>
            </div>
            
            <div class="chat-input">
                <?php if ($conversation['status'] !== 'closed'): ?>
                    <div class="quick-replies mb-3">
                        <p class="mb-2"><strong>Quick Replies:</strong></p>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary quick-reply-btn" 
                                data-reply="Thank you for your message. I'll be happy to help you with your bus rental inquiry.">
                                <i class="fas fa-reply me-1"></i> Greeting
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary quick-reply-btn" 
                                data-reply="Could you please provide more details about your trip requirements? For example, number of passengers, departure/return dates, and destination.">
                                <i class="fas fa-info-circle me-1"></i> Request Details
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary quick-reply-btn" 
                                data-reply="For booking a bus, you can either call us at 1-800-BUS-RENT, email us at bookings@busrental.com, or use our online reservation form on our website.">
                                <i class="fas fa-calendar-check me-1"></i> Booking Info
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary quick-reply-btn" 
                                data-reply="Our pricing varies based on the type of bus, trip duration, and distance. Could you provide more details so I can give you an accurate quote?">
                                <i class="fas fa-tag me-1"></i> Pricing Query
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary quick-reply-btn" 
                                data-reply="Is there anything else I can help you with today?">
                                <i class="fas fa-question-circle me-1"></i> Anything Else
                            </button>
                        </div>
                    </div>
                    <form method="post" action="" class="d-flex">
                        <textarea name="message" id="messageInput" class="form-control me-2" rows="3" placeholder="Type your message here..." required></textarea>
                        <div class="d-flex flex-column">
                            <button type="submit" name="send_message" class="btn btn-success mb-2">
                                <i class="fas fa-paper-plane"></i><br>Send
                            </button>
                            <button type="button" id="quickCloseBtn" class="btn btn-outline-danger d-none" data-bs-toggle="modal" data-bs-target="#closeConversationModal">
                                <i class="fas fa-times"></i><br>Close
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-secondary">
                        <i class="fas fa-lock me-2"></i>
                        This conversation is closed. You cannot send new messages.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Handle closing conversation -->
    <div class="modal fade" id="closeConversationModal" tabindex="-1" aria-labelledby="closeConversationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="closeConversationModalLabel">
                        <i class="fas fa-times-circle me-2"></i> Close Conversation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <p>Are you sure you want to close this conversation?</p>
                        <div class="mb-3">
                            <label for="closingMessage" class="form-label">Closing Message:</label>
                            <textarea class="form-control" id="closingMessage" name="closing_message" rows="4">This conversation has been closed by the customer service agent. If you have additional questions, you can start a new conversation.</textarea>
                            <div class="form-text">This message will be visible to the customer.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quick Closing Messages:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary closing-template" data-message="This conversation has been closed by the customer service agent. If you have additional questions, you can start a new conversation.">
                                    Default
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary closing-template" data-message="Thank you for contacting our bus rental service. Your inquiry has been addressed. If you have any further questions in the future, please don't hesitate to reach out to us again.">
                                    Inquiry Resolved
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary closing-template" data-message="I'm closing this conversation now that your booking has been confirmed. Your reservation details will be sent to your email. Thank you for choosing our bus rental service!">
                                    Booking Confirmed
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary closing-template" data-message="I've answered all your questions about our services. This conversation is now closed. Feel free to start a new chat if you need any other information.">
                                    Information Provided
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="submit" name="close_conversation" class="btn btn-danger">
                            <i class="fas fa-check me-1"></i> Close Conversation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll chat to bottom
        document.addEventListener('DOMContentLoaded', function() {
            var chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            // Set up real-time chat functionality and global polling function
            const { pollNow, forceRefresh } = setupRealTimeChat();
            
            // Poll immediately when page loads for the fastest response
            pollNow();
            
            // Also run a force refresh to ensure we have all messages
            setTimeout(forceRefresh, 500);
            
            // Set up quick reply buttons
            var quickReplyButtons = document.querySelectorAll('.quick-reply-btn');
            var messageInput = document.getElementById('messageInput');
            
            quickReplyButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    messageInput.value = this.getAttribute('data-reply');
                    messageInput.focus();
                });
            });
            
            // Set up close modal focus
            var closeModal = document.getElementById('closeConversationModal');
            if (closeModal) {
                closeModal.addEventListener('shown.bs.modal', function() {
                    document.getElementById('closingMessage').focus();
                });
                
                // Set up quick closing message templates
                var closingTemplates = document.querySelectorAll('.closing-template');
                var closingMessageField = document.getElementById('closingMessage');
                
                closingTemplates.forEach(function(button) {
                    button.addEventListener('click', function() {
                        closingMessageField.value = this.getAttribute('data-message');
                        closingMessageField.focus();
                    });
                });
            }
            
            // Setup form submission via Ajax
            <?php if ($conversation['status'] !== 'closed'): ?>
            const form = document.querySelector('.chat-input form');
            if (form) {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const message = messageInput.value.trim();
                    if (message) {
                        sendAdminMessage(message);
                        messageInput.value = '';
                    }
                });
            }
            
            // Setup close conversation via Ajax
            const closeForm = document.querySelector('#closeConversationForm');
            if (closeForm) {
                closeForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const closingMessage = document.getElementById('closingMessage').value.trim();
                    closeConversation(closingMessage);
                    const closeModal = bootstrap.Modal.getInstance(document.getElementById('closeConversationModal'));
                    closeModal.hide();
                });
            }
            <?php endif; ?>
            
            // Show quick close button when admin types
            var messageInput = document.getElementById('messageInput');
            var quickCloseBtn = document.getElementById('quickCloseBtn');
            if (messageInput && quickCloseBtn) {
                // Show close button when admin types
                messageInput.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        quickCloseBtn.classList.remove('d-none');
                    } else {
                        quickCloseBtn.classList.add('d-none');
                    }
                });
            }
            
            // Add a quick reply for closing
            var quickReplySection = document.querySelector('.quick-replies .d-flex');
            if (quickReplySection) {
                // Create a closing message button
                var closingButton = document.createElement('button');
                closingButton.className = 'btn btn-sm btn-outline-secondary quick-reply-btn';
                closingButton.innerHTML = '<i class="fas fa-door-closed me-1"></i> Closing Message';
                closingButton.setAttribute('data-reply', 'Thank you for contacting our bus rental service. Your inquiry has been addressed. If you have any further questions in the future, please don\'t hesitate to reach out.');
                
                // Add it to the quick replies section
                quickReplySection.appendChild(closingButton);
            }
        });
        
        // Set up real-time chat functionality
        function setupRealTimeChat() {
            // Keep track of the last message ID we've seen
            let lastMessageId = 0;
            
            // Get initial last message ID
            const messages = document.querySelectorAll('.message');
            if (messages.length) {
                // Try to get message IDs from data attributes if they exist
                const lastMessage = messages[messages.length - 1];
                if (lastMessage.hasAttribute('data-message-id')) {
                    const msgId = lastMessage.getAttribute('data-message-id');
                    // Only set lastMessageId if it's not a temporary ID
                    if (!msgId.startsWith('temp-')) {
                        lastMessageId = parseInt(msgId);
                    }
                }
            }
            
            // Setup conversation ID
            const conversationId = <?php echo $conversationId; ?>;
            
            // Poll for new messages frequently (every 500ms)
            const pollInterval = setInterval(pollForNewMessages, 500);
            
            // Also force a periodic deep refresh every 5 seconds to ensure we catch any missed messages
            const deepRefreshInterval = setInterval(forceMessageRefresh, 5000);
            
            // Add a special check just for client messages every 2 seconds
            const clientMessageInterval = setInterval(checkForClientMessages, 2000);
            
            // Setup refresh button
            const refreshButton = document.getElementById('refreshButton');
            if (refreshButton) {
                refreshButton.addEventListener('click', function() {
                    // Show loading state
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Refreshing...';
                    
                    // Force a deep refresh
                    forceMessageRefresh().then(() => {
                        // Restore button state
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                        
                        // Show notification that refresh is complete
                        const chatContainer = document.querySelector('.chat-container');
                        const notification = document.createElement('div');
                        notification.className = 'alert alert-success alert-dismissible fade show m-3';
                        notification.innerHTML = `
                            <i class="fas fa-check-circle me-2"></i> Messages refreshed successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        chatContainer.insertBefore(notification, chatContainer.firstChild);
                        
                        // Auto-dismiss after 3 seconds
                        setTimeout(() => {
                            const bsAlert = new bootstrap.Alert(notification);
                            bsAlert.close();
                        }, 3000);
                    });
                });
            }
            
            // Function to specifically look for recent client messages
            function checkForClientMessages() {
                console.log('Running special client message check...');
                
                const formData = new FormData();
                formData.append('action', 'get_messages');
                formData.append('conversation_id', conversationId);
                formData.append('last_message_id', 0); // Get all messages
                formData.append('client_only', 'true'); // Special flag for client messages
                
                fetch('includes/admin_ajax_handlers.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages && data.messages.length) {
                        // Filter to only client messages
                        const clientMessages = data.messages.filter(msg => msg.sender_type === 'client');
                        
                        if (clientMessages.length > 0) {
                            console.log(`Special check found ${clientMessages.length} client messages`);
                            
                            // Check if any of these messages are not in the DOM
                            let missingCount = 0;
                            let highestClientId = 0;
                            
                            clientMessages.forEach(message => {
                                // Check if this message exists in the DOM
                                const existingMessage = document.querySelector(`[data-message-id="${message.id}"]`);
                                if (!existingMessage) {
                                    console.log(`Special check: Adding missing client message ID ${message.id}`);
                                    addMessageToUI(message);
                                    missingCount++;
                                }
                                
                                // Track highest client message ID
                                if (parseInt(message.id) > highestClientId) {
                                    highestClientId = parseInt(message.id);
                                }
                            });
                            
                            if (missingCount > 0) {
                                console.log(`Special check: Added ${missingCount} missing client messages`);
                                
                                // Play a notification sound for new client messages
                                try {
                                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                                    const oscillator = audioContext.createOscillator();
                                    const gain = audioContext.createGain();
                                    
                                    oscillator.type = 'sine';
                                    oscillator.frequency.value = 1000; // Higher pitch for client messages
                                    gain.gain.value = 0.3;
                                    
                                    oscillator.connect(gain);
                                    gain.connect(audioContext.destination);
                                    
                                    oscillator.start();
                                    oscillator.stop(audioContext.currentTime + 0.2); // 200ms duration
                                } catch (audioError) {
                                    console.log('Unable to play notification sound', audioError);
                                }
                                
                                // Show a visual notification in the chat header
                                const chatHeader = document.querySelector('.chat-header');
                                if (chatHeader) {
                                    const notification = document.createElement('div');
                                    notification.className = 'alert alert-info alert-dismissible fade show mt-2 mb-0';
                                    notification.innerHTML = `
                                        <i class="fas fa-comment me-2"></i> <strong>New client message received!</strong>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    `;
                                    chatHeader.appendChild(notification);
                                    
                                    // Auto-dismiss after 5 seconds
                                    setTimeout(() => {
                                        const bsAlert = new bootstrap.Alert(notification);
                                        bsAlert.close();
                                    }, 5000);
                                    
                                    // Also make the browser tab flash with a notification
                                    let originalTitle = document.title;
                                    let notificationTitle = "🔔 NEW MESSAGE - Admin Chat";
                                    let titleInterval;
                                    
                                    // Flash the title
                                    titleInterval = setInterval(() => {
                                        document.title = document.title === originalTitle ? notificationTitle : originalTitle;
                                    }, 1000);
                                    
                                    // Stop flashing after 10 seconds or when tab gets focus
                                    setTimeout(() => {
                                        clearInterval(titleInterval);
                                        document.title = originalTitle;
                                    }, 10000);
                                    
                                    // Also stop flashing when tab gets focus
                                    window.addEventListener('focus', function onFocus() {
                                        clearInterval(titleInterval);
                                        document.title = originalTitle;
                                        window.removeEventListener('focus', onFocus);
                                    });
                                }
                            }
                            
                            // If highest client message ID is greater than our tracking ID, update it
                            if (highestClientId > lastMessageId) {
                                console.log(`Special check: Updating lastMessageId from ${lastMessageId} to ${highestClientId}`);
                                lastMessageId = highestClientId;
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error in special client message check:', error);
                });
            }
            
            // Function to force refresh all messages, regardless of last message ID
            function forceMessageRefresh() {
                console.log('Force refreshing all messages to ensure consistency...');
                
                const formData = new FormData();
                formData.append('action', 'get_messages');
                formData.append('conversation_id', conversationId);
                formData.append('last_message_id', 0); // Request all messages
                
                return fetch('includes/admin_ajax_handlers.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages && data.messages.length) {
                        console.log(`Force refresh found ${data.messages.length} messages total`);
                        
                        // Count how many messages are missing from the DOM
                        let missingCount = 0;
                        let highestId = 0;
                        
                        data.messages.forEach(message => {
                            // Check if this message already exists in the DOM
                            const existingMessage = document.querySelector(`[data-message-id="${message.id}"]`);
                            if (!existingMessage) {
                                console.log(`Force refresh: Adding missing message ID ${message.id} (${message.sender_type})`);
                                addMessageToUI(message);
                                missingCount++;
                            }
                            
                            // Track highest ID
                            if (parseInt(message.id) > highestId) {
                                highestId = parseInt(message.id);
                            }
                        });
                        
                        if (missingCount > 0) {
                            console.log(`Force refresh: Added ${missingCount} missing messages`);
                        }
                        
                        // Ensure lastMessageId is updated to the highest value
                        if (highestId > lastMessageId) {
                            console.log(`Force refresh: Updated lastMessageId from ${lastMessageId} to ${highestId}`);
                            lastMessageId = highestId;
                        }
                        
                        // Return true if we added any missing messages
                        return missingCount > 0;
                    }
                    return false;
                })
                .catch(error => {
                    console.error('Error in force refresh:', error);
                    return false;
                });
            }
            
            // Function to poll for new messages
            function pollForNewMessages() {
                // Show polling indicator
                const pollingIndicator = document.getElementById('pollingIndicator');
                if (pollingIndicator) {
                    pollingIndicator.classList.add('active');
                }
                
                const formData = new FormData();
                formData.append('action', 'get_messages');
                formData.append('conversation_id', conversationId);
                formData.append('last_message_id', lastMessageId);
                
                fetch('includes/admin_ajax_handlers.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Hide polling indicator
                    if (pollingIndicator) {
                        pollingIndicator.classList.remove('active');
                    }
                    
                    if (data.success) {
                        console.log('Received new messages:', data.messages);
                        
                        // Add new messages to UI
                        if (data.messages && data.messages.length) {
                            console.log(`Found ${data.messages.length} new messages, last message ID was: ${lastMessageId}`);
                            
                            let foundNewMessages = false;
                            
                            data.messages.forEach(message => {
                                // Check if this message already exists in the DOM
                                const existingMessage = document.querySelector(`[data-message-id="${message.id}"]`);
                                if (!existingMessage) {
                                    console.log(`Adding message to UI: ${message.sender_type} - ${message.id}`);
                                    
                                    // Special handling for client messages - log and ensure they're visible
                                    if (message.sender_type === 'client') {
                                        console.log('⚠️ CLIENT MESSAGE DETECTED! Ensuring it shows in UI: ', message);
                                    }
                                    
                                    addMessageToUI(message);
                                    foundNewMessages = true;
                                } else {
                                    console.log(`Message already exists in DOM: ${message.id}`);
                                }
                                
                                // Always update lastMessageId to the highest seen
                                if (parseInt(message.id) > lastMessageId) {
                                    lastMessageId = parseInt(message.id);
                                }
                            });
                            
                            if (foundNewMessages) {
                                // Play a notification sound for new messages
                                try {
                                    // Try to create a notification sound even if file is missing
                                    // This creates a short beep as fallback
                                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                                    const oscillator = audioContext.createOscillator();
                                    const gain = audioContext.createGain();
                                    
                                    oscillator.type = 'sine';
                                    oscillator.frequency.value = 800; // Hz
                                    gain.gain.value = 0.2;
                                    
                                    oscillator.connect(gain);
                                    gain.connect(audioContext.destination);
                                    
                                    oscillator.start();
                                    oscillator.stop(audioContext.currentTime + 0.1); // 100ms duration
                                } catch (audioError) {
                                    console.log('Unable to play notification sound', audioError);
                                }
                                
                                // Ensure messages container is scrolled to bottom
                                const chatMessages = document.getElementById('chatMessages');
                                if (chatMessages) {
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                            }
                            
                            console.log(`Updated lastMessageId to: ${lastMessageId}`);
                        } else {
                            console.log('No new messages');
                            
                            // If we've done multiple polls with no new messages, check the highest message ID in the DOM
                            // This helps recover in case of sync issues
                            const allMessages = document.querySelectorAll('.message[data-message-id]');
                            if (allMessages.length) {
                                let highestId = 0;
                                allMessages.forEach(msg => {
                                    const msgId = msg.getAttribute('data-message-id');
                                    if (!msgId.startsWith('temp-')) {
                                        const id = parseInt(msgId);
                                        if (id > highestId) {
                                            highestId = id;
                                        }
                                    }
                                });
                                
                                // If the DOM has a higher ID than what we're tracking, update it
                                if (highestId > lastMessageId) {
                                    console.log(`Resetting lastMessageId from ${lastMessageId} to ${highestId} based on DOM content`);
                                    lastMessageId = highestId;
                                }
                            }
                        }
                        
                        // Check if conversation status has changed
                        if (data.status === 'closed') {
                            // Update UI for closed conversation
                            updateUIForClosedConversation();
                        }
                    } else {
                        console.error('Error in poll response:', data);
                    }
                })
                .catch(error => {
                    console.error('Error polling for new messages:', error);
                });
            }
            
            // Return functions that need to be accessible from outside
            return {
                pollNow: pollForNewMessages,
                forceRefresh: forceMessageRefresh
            };
        }
        
        // Send admin message via Ajax
        function sendAdminMessage(message) {
            // Generate a temporary ID for this message
            const tempId = 'temp-' + Date.now();
            
            // Add message to the UI immediately (optimistic UI update)
            addMessageToUI({
                id: tempId,
                content: message,
                sender_type: 'admin',
                sender_name: 'You (Agent)'
            });
            
            // Send message to server
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('conversation_id', <?php echo $conversationId; ?>);
            formData.append('message', message);
            
            fetch('includes/admin_ajax_handlers.php', {
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
                    
                    // If there's a system message (e.g., admin joined notification), add it
                    if (data.system_message) {
                        addMessageToUI(data.system_message);
                    }
                } else {
                    console.error('Error sending message:', data.message);
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
            });
        }
        
        // Close conversation via Ajax
        function closeConversation(closingMessage) {
            const formData = new FormData();
            formData.append('action', 'close_conversation');
            formData.append('conversation_id', <?php echo $conversationId; ?>);
            formData.append('closing_message', closingMessage);
            
            fetch('includes/admin_ajax_handlers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add closing message to UI
                    addMessageToUI(data.closing_message);
                    
                    // Update UI for closed conversation
                    updateUIForClosedConversation();
                    
                    // Show a success message
                    alert('The conversation has been closed successfully.');
                    
                    // Redirect back to admin dashboard after a delay
                    setTimeout(function() {
                        window.location.href = 'admin.php';
                    }, 3000);
                } else {
                    console.error('Error closing conversation:', data.message);
                }
            })
            .catch(error => {
                console.error('Error closing conversation:', error);
            });
        }
        
        // Add a message to the UI
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
            
            // Add data-message-id attribute if there's an ID
            if (message.id) {
                messageDiv.setAttribute('data-message-id', message.id);
            }
            
            const formattedDate = message.sent_at ? formatDateTime(message.sent_at) : '';
            
            // Handle both message.content and message.message formats
            const messageContent = message.content || message.message || '';
            
            messageDiv.innerHTML = `
                <div class="message-content">
                    <div><small class="text-muted">${message.sender_name}${formattedDate ? ' - ' + formattedDate : ''}</small></div>
                    ${messageContent}
                </div>
            `;
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Log that a message was added successfully
            console.log(`Successfully added message: ${message.id} (${message.sender_type})`);
        }
        
        // Format date/time for display
        function formatDateTime(dateString) {
            if (!dateString) return '';
            
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                hour12: true
            });
        }
        
        // Update UI for closed conversation
        function updateUIForClosedConversation() {
            const chatInput = document.querySelector('.chat-input');
            if (chatInput) {
                chatInput.innerHTML = `
                    <div class="alert alert-secondary">
                        <i class="fas fa-lock me-2"></i>
                        This conversation is closed. You cannot send new messages.
                    </div>
                `;
            }
        }
    </script>
</body>
</html> 