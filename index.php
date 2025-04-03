<?php
// Include required files
require_once 'config/database.php';
require_once 'includes/chatbot.php';

// Initialize session if needed for complex questions
session_start();

// Create a default conversation
$conversationId = null;

// Check if there's a session conversation ID
if (!isset($_SESSION['conversation_id'])) {
    // Create a new conversation with a system user ID of 1
    try {
        // First check if the users table has at least one user
        $userExists = getRow("SELECT id FROM users LIMIT 1");
        
        if (!$userExists) {
            // Insert a default user if none exists
            $defaultUserId = insertData(
                "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)",
                ['guest', password_hash('guest', PASSWORD_DEFAULT), 'guest@example.com', 'client'],
                "ssss"
            );
            $clientId = $defaultUserId;
        } else {
            // Use the first user as the client
            $clientId = $userExists['id']; 
        }
        
        // Create conversation
        $conversationId = insertData(
            "INSERT INTO conversations (client_id, status) VALUES (?, 'bot')",
            [$clientId],
            "i"
        );
        
        // Add welcome message
        addMessage($conversationId, 'bot', 'Welcome to our Bus Rental service! How can I assist you today? You can use the quick question buttons above or type your own question.');
        
        // Store conversation ID in session
        $_SESSION['conversation_id'] = $conversationId;
    } catch (Exception $e) {
        // If there's an error, show a friendly message
        $error = "Sorry, there was an issue setting up the chat. Please make sure your database is set up correctly.";
        echo $error;
        exit;
    }
} else {
    // Use existing conversation
    $conversationId = $_SESSION['conversation_id'];
}

// Get current conversation status
$conversationStatus = getConversationStatus($conversationId);

// Handle predefined button click
if (isset($_GET['ask_question'])) {
    $predefinedQuestion = urldecode($_GET['ask_question']);
    
    // Add client message to conversation
    addMessage($conversationId, 'client', $predefinedQuestion);
    
    // Get the latest conversation status again (in case it changed)
    $conversationStatus = getConversationStatus($conversationId);
    
    // Only process with bot if not already talking to a human
    if ($conversationStatus !== 'human_assigned') {
        // Process with bot, passing the conversation ID
        $botResponse = processBotMessage($predefinedQuestion, $conversationId);
        
        if ($botResponse !== null) {
            // Bot can handle the question
            addMessage($conversationId, 'bot', $botResponse);
        } else {
            // Bot cannot handle the question, provide option to connect with a human agent
            $complexMessage = "I'm sorry, but I don't have enough information to answer your question properly. Would you like to talk to a customer service representative who can help you better?";
            
            // Add the complex message with buttons
            addMessage($conversationId, 'bot', $complexMessage . ' <div class="mt-2"><a href="index.php?connect_to_admin=1" class="btn btn-sm btn-primary">Yes, connect me with an agent</a> <a href="index.php" class="btn btn-sm btn-outline-secondary">No, I\'ll ask something else</a></div>');
        }
    }
    
    // Redirect to prevent resubmission
    header('Location: index.php');
    exit;
}

// Handle connect to admin request
if (isset($_GET['connect_to_admin'])) {
    // Get the problem description if provided
    $problemDescription = isset($_GET['problem']) ? $_GET['problem'] : '';
    
    // Add the user's question/problem as a client message
    if (!empty($problemDescription)) {
        addMessage($conversationId, 'client', $problemDescription);
    }
    
    // Update conversation status to human_requested for admin attention
    requestHumanAssistance($conversationId);
    
    // Add a message explaining that an admin will be connected
    $adminMessage = "Thank you for your patience. I'm connecting you with one of our customer service representatives who will be able to help you better. Please wait a moment while I transfer your conversation to an available agent. They'll join the chat as soon as possible.";
    addMessage($conversationId, 'bot', $adminMessage);
    
    // Redirect to prevent resubmission
    header('Location: index.php');
    exit;
}

// Handle chat message submission
if (isset($_POST['send_message'])) {
    $message = $_POST['message'];
    
    // Add client message to conversation
    addMessage($conversationId, 'client', $message);
    
    // Get the latest conversation status again (in case it changed)
    $conversationStatus = getConversationStatus($conversationId);
    
    // Only process with bot if not talking to a human
    if ($conversationStatus !== 'human_assigned') {
        // Process with bot, passing the conversation ID
        $botResponse = processBotMessage($message, $conversationId);
        
        if ($botResponse !== null) {
            // Bot can handle the question
            addMessage($conversationId, 'bot', $botResponse);
        } else {
            // Bot cannot handle the question, provide option to connect with a human agent
            $complexMessage = "I'm sorry, but I don't have enough information to answer your question properly. Would you like to talk to a customer service representative who can help you better?";
            
            // Add the complex message with buttons
            addMessage($conversationId, 'bot', $complexMessage . ' <div class="mt-2"><a href="index.php?connect_to_admin=1" class="btn btn-sm btn-primary">Yes, connect me with an agent</a> <a href="index.php" class="btn btn-sm btn-outline-secondary">No, I\'ll ask something else</a></div>');
        }
    }
    // If human assigned, don't add a bot response - just let the admin respond
    
    // Redirect to prevent form resubmission
    header('Location: index.php');
    exit;
}

// Get all messages for this conversation
$messages = getConversationMessages($conversationId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus Rental Chat Service</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-bus me-2"></i>
                Bus Rental Chat Service
            </a>
        </div>
    </nav>

    <div class="container my-5">
        <div class="chat-container">
            <div class="chat-header">
                <h4 class="mb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-comment-dots me-2"></i>
                        <?php if ($conversationStatus === 'human_assigned'): ?>
                        Bus Rental Customer Service
                        <?php else: ?>
                        Bus Rental Assistant
                        <?php endif; ?>
                    </div>
                    <?php if ($conversationStatus !== 'human_assigned' && $conversationStatus !== 'human_requested'): ?>
                    <a href="#" onclick="confirmAssistance(event)" class="btn btn-sm btn-primary btn-assistance" title="Request human assistance">
                        <i class="fas fa-headset me-1"></i>
                        <span class="btn-text">Talk to a Human Agent</span>
                    </a>
                    <?php endif; ?>
                </h4>
            </div>
            
            <!-- Add confirmation modal for human assistance -->
            <div class="modal fade" id="assistanceModal" tabindex="-1" aria-labelledby="assistanceModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="assistanceModalLabel">Connect with a Human Agent</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Would you like to connect with a human customer service agent? They can help with complex questions or provide personalized assistance.</p>
                            <form id="problemForm">
                                <div class="mb-3">
                                    <label for="problemDescription" class="form-label">Briefly describe your question (optional):</label>
                                    <textarea class="form-control" id="problemDescription" rows="3" placeholder="What would you like help with?"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="connectToAdmin()">Connect with Agent</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick question buttons -->
            <?php if ($conversationStatus !== 'human_assigned'): ?>
            <div class="quick-buttons">
                <a href="index.php?ask_question=<?php echo urlencode('How do I book a bus?'); ?>" class="btn btn-sm btn-outline-primary quick-btn">
                    <i class="fas fa-calendar-check me-1"></i> <span class="btn-label">How to Book</span>
                </a>
                <a href="index.php?ask_question=<?php echo urlencode('What are your pricing options?'); ?>" class="btn btn-sm btn-outline-primary quick-btn">
                    <i class="fas fa-tag me-1"></i> <span class="btn-label">Pricing</span>
                </a>
                <a href="index.php?ask_question=<?php echo urlencode('What types of buses do you offer?'); ?>" class="btn btn-sm btn-outline-primary quick-btn">
                    <i class="fas fa-bus me-1"></i> <span class="btn-label">Bus Types</span>
                </a>
                <a href="index.php?ask_question=<?php echo urlencode('How do I cancel my reservation?'); ?>" class="btn btn-sm btn-outline-primary quick-btn">
                    <i class="fas fa-ban me-1"></i> <span class="btn-label">Cancellation</span>
                </a>
                <a href="index.php?ask_question=<?php echo urlencode('How can I contact customer service?'); ?>" class="btn btn-sm btn-outline-primary quick-btn">
                    <i class="fas fa-phone me-1"></i> <span class="btn-label">Contact Us</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Display conversation status if needed -->
            <?php if ($conversationStatus === 'human_requested'): ?>
            <div class="conversation-status status-human-requested">
                <div class="d-flex align-items-center p-3 bg-warning-subtle border border-warning rounded">
                    <div class="spinner-border spinner-border-sm text-warning me-2" role="status">
                        <span class="visually-hidden">Connecting...</span>
                    </div>
                    <div><strong>Connecting to an agent...</strong> Our customer service team has been notified. Please wait while an available agent joins your conversation. This usually takes less than 2 minutes during business hours.</div>
                </div>
            </div>
            <?php elseif ($conversationStatus === 'human_assigned'): ?>
            <div class="conversation-status status-human-assigned">
                <div class="d-flex align-items-center p-3 bg-success-subtle border border-success rounded">
                    <div class="text-success me-2"><i class="fas fa-user-check"></i></div>
                    <div><strong>Agent connected!</strong> You are now chatting with a customer service representative who will assist you with your inquiry.</div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="chat-messages" id="chatMessages">
                <?php
                foreach ($messages as $message) {
                    // Handle bot messages differently when admin is assigned
                    if ($conversationStatus === 'human_assigned' && $message['sender_type'] === 'bot') {
                        // Only show the "customer service agent has joined" message, hide all other bot messages
                        $joinMessage = false;
                        $joinPhrases = ['service agent has joined', 'customer service representative', 'has joined the conversation'];
                        
                        foreach ($joinPhrases as $phrase) {
                            if (strpos($message['message'], $phrase) !== false) {
                                $joinMessage = true;
                                break;
                            }
                        }
                        
                        // Skip all bot messages except join notifications when admin is assigned
                        if (!$joinMessage) {
                            continue;
                        }
                    }
                    
                    $messageClass = $message['sender_type'] . '-message';
                    $senderName = $message['sender_type'] === 'bot' ? 'Bus Rental Bot' : 'You';
                    if ($message['sender_type'] === 'admin') {
                        $senderName = 'Customer Service';
                    }
                    
                    // Add proper HTML structure and classes
                    echo '<div class="message ' . $messageClass . '">';
                    echo '<div class="message-content">';
                    
                    // Direct styling for client messages to completely avoid Bootstrap's text-muted
                    if ($message['sender_type'] === 'client') {
                        echo '<div class="message-meta"><span class="sender-name">' . htmlspecialchars($senderName) . '</span></div>';
                    } else {
                        echo '<div class="message-meta"><small class="text-muted">' . htmlspecialchars($senderName) . '</small></div>';
                    }
                    
                    // Process message content but allow HTML in messages for buttons
                    // For bot messages where we need to allow HTML for buttons
                    if ($message['sender_type'] === 'bot') {
                        $messageContent = $message['message'];
                    } else {
                        // For client and admin messages, escape HTML
                        $messageContent = nl2br(htmlspecialchars($message['message']));
                    }
                    
                    // Replace empty messages with a space to ensure they're visible
                    if (trim(strip_tags($messageContent)) === '') {
                        $messageContent = '&nbsp;';
                    }
                    
                    // Wrap the message text in a div with proper styling
                    echo '<div class="message-text">' . $messageContent . '</div>';
                    echo '</div></div>';
                }
                ?>
            </div>
            <div class="chat-input">
                <form method="post" action="" class="d-flex">
                    <textarea name="message" class="form-control me-2" rows="1" placeholder="Type your message here..." required <?php echo ($conversationStatus === 'human_requested' && !isset($_GET['connect_to_admin'])) ? 'disabled placeholder="Waiting for an agent to connect..."' : ''; ?>></textarea>
                    <button type="submit" name="send_message" class="btn btn-primary" <?php echo ($conversationStatus === 'human_requested' && !isset($_GET['connect_to_admin'])) ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="info-box">
            <h5>About Our Bus Rental Service</h5>
            <p>We offer various bus rental options for all your transportation needs. You can ask our bot about:</p>
            <ul>
                <li>Pricing and rates</li>
                <li>Booking process</li>
                <li>Cancellation policy</li>
                <li>Our fleet of vehicles</li>
                <li>Contact information</li>
            </ul>
            <p class="mb-0"><small>For more complex inquiries, our bot will connect you with a customer service representative.</small></p>
        </div>
        
        <div class="admin-link">
            <a href="admin.php" class="text-muted">Admin Access</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll to bottom of chat on page load
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();
        });
        
        // Function to scroll chat to bottom
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Show confirmation modal for assistance
        function confirmAssistance(event) {
            event.preventDefault();
            var myModal = new bootstrap.Modal(document.getElementById('assistanceModal'));
            myModal.show();
        }
        
        // Connect to admin with problem description
        function connectToAdmin() {
            const problemDescription = document.getElementById('problemDescription').value;
            let url = 'index.php?connect_to_admin=1';
            
            if (problemDescription.trim() !== '') {
                url += '&problem=' + encodeURIComponent(problemDescription);
            }
            
            window.location.href = url;
        }
    </script>
</body>
</html> 