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

// Handle accepting a conversation
if (isset($_POST['accept_conversation'])) {
    $conversationId = $_POST['conversation_id'];
    $adminId = $currentUser['id'];
    
    // Assign admin to conversation
    assignAdminToConversation($conversationId, $adminId);
    
    // Add system message
    addMessage($conversationId, 'bot', "A customer service agent has joined the conversation.");
    
    // Redirect to prevent form resubmission
    header('Location: admin_chat.php?id=' . $conversationId);
    exit;
}

// Get all conversations that need human assistance
$requestedConversations = getRows(
    "SELECT c.*, u.username as client_name, 
    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count,
    (SELECT MAX(sent_at) FROM messages WHERE conversation_id = c.id) as last_message,
    (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) as latest_message
    FROM conversations c
    JOIN users u ON c.client_id = u.id
    WHERE c.status = 'human_requested'
    ORDER BY c.updated_at DESC"
);

// Get active conversations that this admin is handling
$activeConversations = getRows(
    "SELECT c.*, u.username as client_name, 
    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count,
    (SELECT MAX(sent_at) FROM messages WHERE conversation_id = c.id) as last_message,
    (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) as latest_message
    FROM conversations c
    JOIN users u ON c.client_id = u.id
    WHERE c.status = 'human_assigned' AND c.admin_id = ?
    ORDER BY c.updated_at DESC",
    [$currentUser['id']],
    "i"
);

// Get recent closed conversations
$recentClosedConversations = getRows(
    "SELECT c.*, u.username as client_name, 
    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count,
    (SELECT MAX(sent_at) FROM messages WHERE conversation_id = c.id) as last_message,
    (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) as latest_message
    FROM conversations c
    JOIN users u ON c.client_id = u.id
    WHERE c.status = 'closed'
    ORDER BY c.updated_at DESC
    LIMIT 10"
);

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
    <title>Admin Panel - Bus Rental Chat Service</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .conversation-item {
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .conversation-item:hover {
            background-color: #e9ecef;
        }
        .badge-human-requested {
            background-color: #dc3545;
            color: white;
        }
        .badge-human-assigned {
            background-color: #28a745;
            color: white;
        }
        .badge-closed {
            background-color: #6c757d;
            color: white;
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
                        <a class="nav-link active" href="admin.php">
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
                        <a class="nav-link" href="admin.php">
                            <i class="fas fa-sync-alt me-1"></i>
                            Refresh
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-12 mb-4">
                <h2>
                    <i class="fas fa-cog me-2"></i>
                    Admin Panel
                </h2>
                <p class="text-muted">Manage customer conversations and provide assistance.</p>
            </div>
        </div>

        <!-- Conversations requiring assistance -->
        <div class="row">
            <div class="col-md-12">
                <div class="admin-container">
                    <h4>
                        <i class="fas fa-exclamation-circle text-danger me-2"></i>
                        Conversations Requiring Assistance
                        <?php if (count($requestedConversations) > 0): ?>
                            <span class="badge bg-danger"><?php echo count($requestedConversations); ?></span>
                        <?php endif; ?>
                    </h4>
                    
                    <?php if (count($requestedConversations) > 0): ?>
                        <div class="alert alert-danger mt-3">
                            <i class="fas fa-bell me-2"></i> You have <strong><?php echo count($requestedConversations); ?></strong> customer(s) waiting for human assistance. Please respond promptly.
                        </div>
                        <div class="list-group mt-3">
                            <?php foreach ($requestedConversations as $conversation): ?>
                                <div class="conversation-item border-danger">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1">Conversation with <?php echo htmlspecialchars($conversation['client_name']); ?></h5>
                                            <p class="mb-1 text-muted">
                                                <small>
                                                    <i class="fas fa-clock me-1"></i>
                                                    Started: <?php echo formatDate($conversation['started_at']); ?>
                                                </small>
                                                <br>
                                                <small>
                                                    <i class="fas fa-comment me-1"></i>
                                                    Last message: <?php echo formatDate($conversation['last_message']); ?>
                                                </small>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Latest message:</strong> 
                                                <?php 
                                                echo strlen($conversation['latest_message']) > 100 
                                                    ? htmlspecialchars(substr($conversation['latest_message'], 0, 100)) . '...' 
                                                    : htmlspecialchars($conversation['latest_message']); 
                                                ?>
                                            </p>
                                            <span class="badge bg-danger">Customer waiting</span>
                                            <span class="badge bg-secondary"><?php echo $conversation['message_count']; ?> messages</span>
                                        </div>
                                        <div>
                                            <form method="post" action="">
                                                <input type="hidden" name="conversation_id" value="<?php echo $conversation['id']; ?>">
                                                <button type="submit" name="accept_conversation" class="btn btn-primary">
                                                    <i class="fas fa-user-check me-1"></i>
                                                    Accept Conversation
                                                </button>
                                            </form>
                                            <a href="admin_chat.php?id=<?php echo $conversation['id']; ?>" class="btn btn-outline-secondary mt-2">
                                                <i class="fas fa-eye me-1"></i>
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            No conversations currently require assistance.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Your active conversations -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="admin-container">
                    <h4>
                        <i class="fas fa-comments text-success me-2"></i>
                        Your Active Conversations
                        <?php if (count($activeConversations) > 0): ?>
                            <span class="badge bg-success"><?php echo count($activeConversations); ?></span>
                        <?php endif; ?>
                    </h4>
                    
                    <?php if (count($activeConversations) > 0): ?>
                        <div class="list-group mt-3">
                            <?php foreach ($activeConversations as $conversation): ?>
                                <div class="conversation-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1">Conversation with <?php echo htmlspecialchars($conversation['client_name']); ?></h5>
                                            <p class="mb-1 text-muted">
                                                <small>
                                                    <i class="fas fa-clock me-1"></i>
                                                    Started: <?php echo formatDate($conversation['started_at']); ?>
                                                </small>
                                                <br>
                                                <small>
                                                    <i class="fas fa-comment me-1"></i>
                                                    <?php echo $conversation['message_count']; ?> messages
                                                </small>
                                                <br>
                                                <small>
                                                    <i class="fas fa-history me-1"></i>
                                                    Last activity: <?php echo formatDate($conversation['last_message']); ?>
                                                </small>
                                            </p>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <small><strong>Latest message:</strong> 
                                                <?php 
                                                $messagePreview = htmlspecialchars(substr($conversation['latest_message'], 0, 100));
                                                $hasFullMessage = strlen($conversation['latest_message']) > 100;
                                                echo $messagePreview . ($hasFullMessage ? '...' : ''); 
                                                
                                                if ($hasFullMessage): ?>
                                                <button type="button" class="btn btn-sm btn-link p-0 ms-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#messageModal<?php echo $conversation['id']; ?>">
                                                    View Full Message
                                                </button>
                                                
                                                <!-- Modal for full message -->
                                                <div class="modal fade" id="messageModal<?php echo $conversation['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Full Message</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <?php echo nl2br(htmlspecialchars($conversation['latest_message'])); ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <a href="admin_chat.php?id=<?php echo $conversation['id']; ?>&assign=1" class="btn btn-primary">
                                                                    Respond to This Conversation
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="admin_chat.php?id=<?php echo $conversation['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-comments me-1"></i>
                                                Continue Chat
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            You don't have any active conversations.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent closed conversations -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="admin-container">
                    <h4>
                        <i class="fas fa-archive text-secondary me-2"></i>
                        Recent Closed Conversations
                    </h4>
                    
                    <?php if (count($recentClosedConversations) > 0): ?>
                        <div class="list-group mt-3">
                            <?php foreach ($recentClosedConversations as $conversation): ?>
                                <div class="conversation-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1">Conversation with <?php echo htmlspecialchars($conversation['client_name']); ?></h5>
                                            <p class="mb-1 text-muted">
                                                <small>
                                                    <i class="fas fa-clock me-1"></i>
                                                    Started: <?php echo formatDate($conversation['started_at']); ?>
                                                </small>
                                                <br>
                                                <small>
                                                    <i class="fas fa-comment me-1"></i>
                                                    <?php echo $conversation['message_count']; ?> messages
                                                </small>
                                                <br>
                                                <small>
                                                    <i class="fas fa-history me-1"></i>
                                                    Last activity: <?php echo formatDate($conversation['last_message']); ?>
                                                </small>
                                            </p>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <small><strong>Latest message:</strong> 
                                                <?php 
                                                $messagePreview = htmlspecialchars(substr($conversation['latest_message'], 0, 100));
                                                $hasFullMessage = strlen($conversation['latest_message']) > 100;
                                                echo $messagePreview . ($hasFullMessage ? '...' : ''); 
                                                
                                                if ($hasFullMessage): ?>
                                                <button type="button" class="btn btn-sm btn-link p-0 ms-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#messageModal<?php echo $conversation['id']; ?>">
                                                    View Full Message
                                                </button>
                                                
                                                <!-- Modal for full message -->
                                                <div class="modal fade" id="messageModal<?php echo $conversation['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Full Message</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <?php echo nl2br(htmlspecialchars($conversation['latest_message'])); ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <a href="admin_chat.php?id=<?php echo $conversation['id']; ?>" class="btn btn-primary">
                                                                    View Full Conversation
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <a href="admin_chat.php?id=<?php echo $conversation['id']; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-eye me-1"></i>
                                            View
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            No closed conversations found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Periodically check for new conversations (every 30 seconds)
        const initialCount = <?php echo count($requestedConversations); ?>;
        let lastCount = initialCount;
        
        // Create an audio notification element
        const audioNotification = new Audio('assets/notification.mp3');
        
        // Add notification element to the page
        document.body.insertAdjacentHTML('beforeend', 
            `<div id="newConversationAlert" class="toast align-items-center text-white bg-danger position-fixed bottom-0 end-0 m-4" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-bell me-2"></i>
                        <strong>New conversation requiring assistance!</strong> 
                        <br>A customer is waiting for help.
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>`
        );
        
        const toast = new bootstrap.Toast(document.getElementById('newConversationAlert'), {
            autohide: false
        });
        
        // Function to check for new conversations
        function checkForNewConversations() {
            fetch('check_new_conversations.php')
                .then(response => response.json())
                .then(data => {
                    const newCount = data.count;
                    
                    // If there are more conversations than before, show notification
                    if (newCount > lastCount) {
                        // Play sound
                        audioNotification.play();
                        
                        // Show toast notification
                        toast.show();
                        
                        // Update the page title to indicate new conversations
                        document.title = `(${newCount}) New Conversations - Admin Panel`;
                    }
                    
                    // Update the count for next check
                    lastCount = newCount;
                })
                .catch(error => console.error('Error checking for new conversations:', error));
        }
        
        // Check every 30 seconds
        setInterval(checkForNewConversations, 30000);
        
        // Also check when the page regains focus
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                checkForNewConversations();
            }
        });
    </script>
</body>
</html> 