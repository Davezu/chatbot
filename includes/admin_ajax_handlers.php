<?php
/**
 * Admin Ajax handler for chat functionality
 * This file processes ajax requests for the admin chat system
 */

// Include required files
require_once 'chatbot.php';
require_once '../config/database.php';
require_once 'auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Make sure the request is an Ajax request
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Function to return JSON response
function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Check if action is specified
if (!isset($_POST['action'])) {
    sendJsonResponse(['success' => false, 'message' => 'No action specified'], 400);
}

$action = $_POST['action'];
$currentUser = getCurrentUser();

// Handle different actions
switch ($action) {
    case 'send_message':
        // Check for required parameters
        if (!isset($_POST['message']) || !isset($_POST['conversation_id'])) {
            sendJsonResponse(['success' => false, 'message' => 'Missing parameters'], 400);
        }
        
        $message = $_POST['message'];
        $conversationId = $_POST['conversation_id'];
        
        // Get conversation details
        $conversation = getRow(
            "SELECT * FROM conversations WHERE id = ?",
            [$conversationId],
            "i"
        );
        
        if (!$conversation) {
            sendJsonResponse(['success' => false, 'message' => 'Conversation not found'], 404);
        }
        
        // If this is the first response and the admin hasn't been assigned yet,
        // assign this admin to the conversation
        if ($conversation['status'] === 'human_requested' && $conversation['admin_id'] === null) {
            assignAdminToConversation($conversationId, $currentUser['id']);
            
            // Add system message about admin joining
            $joinMessage = "Customer service representative " . htmlspecialchars($currentUser['username']) . " has joined the conversation and will assist you shortly.";
            $systemMessageId = addMessage($conversationId, 'bot', $joinMessage);
            
            // Return the system message as well
            $systemMessage = [
                'id' => $systemMessageId,
                'content' => $joinMessage,
                'sender_type' => 'bot',
                'sender_name' => 'System'
            ];
        }
        
        // Add admin message to conversation
        $messageId = addMessage($conversationId, 'admin', $message);
        
        $response = [
            'success' => true,
            'message_id' => $messageId,
            'status' => getConversationStatus($conversationId)
        ];
        
        // Add system message if it was generated
        if (isset($systemMessage)) {
            $response['system_message'] = $systemMessage;
        }
        
        sendJsonResponse($response);
        break;
        
    case 'get_messages':
        // Check for required parameters
        if (!isset($_POST['conversation_id'])) {
            sendJsonResponse(['success' => false, 'message' => 'Missing conversation ID'], 400);
        }
        
        $conversationId = $_POST['conversation_id'];
        $lastMessageId = isset($_POST['last_message_id']) ? (int)$_POST['last_message_id'] : 0;
        $clientOnly = isset($_POST['client_only']) && $_POST['client_only'] === 'true';
        
        // Get conversation details to check permission
        $conversation = getRow(
            "SELECT * FROM conversations WHERE id = ?",
            [$conversationId],
            "i"
        );
        
        if (!$conversation) {
            sendJsonResponse(['success' => false, 'message' => 'Conversation not found'], 404);
        }
        
        // Only allow if this admin is assigned or if conversation is not assigned yet
        if ($conversation['status'] === 'human_assigned' && $conversation['admin_id'] != $currentUser['id'] && $conversation['status'] !== 'closed') {
            sendJsonResponse(['success' => false, 'message' => 'Not authorized for this conversation'], 403);
        }
        
        // If client_only flag is set, prioritize fetching client messages
        if ($clientOnly) {
            error_log("Admin poll: Running special client message check");
            
            // Get only client messages from the last 2 minutes
            $messages = getRows(
                "SELECT * FROM messages WHERE conversation_id = ? AND sender_type = 'client' AND sent_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE) ORDER BY id ASC",
                [$conversationId],
                "i"
            );
            
            error_log("Admin poll: Special client check found " . count($messages) . " client messages");
            
            // If we didn't find any recent client messages, at least get the most recent one
            if (empty($messages)) {
                $latestClientMessage = getRow(
                    "SELECT * FROM messages WHERE conversation_id = ? AND sender_type = 'client' ORDER BY id DESC LIMIT 1",
                    [$conversationId],
                    "i"
                );
                
                if ($latestClientMessage) {
                    error_log("Admin poll: Found latest client message ID {$latestClientMessage['id']}");
                    $messages[] = $latestClientMessage;
                }
            }
        } else {
            // Normal message retrieval
            $messages = getNewMessages($conversationId, $lastMessageId);
            
            // Debug: Log message retrieval
            error_log("Admin poll: Found " . count($messages) . " new messages for conversation $conversationId (since ID $lastMessageId)");
            
            // If no messages found but there might be new client messages, check specifically for the most recent client message
            if (empty($messages) || $lastMessageId === 0) {
                // Get the latest client message regardless of ID to ensure we don't miss anything
                $latestClientMessage = getRow(
                    "SELECT * FROM messages WHERE conversation_id = ? AND sender_type = 'client' ORDER BY id DESC LIMIT 1",
                    [$conversationId],
                    "i"
                );
                
                // If the latest client message exists and is newer than our lastMessageId, add it to the messages array
                if ($latestClientMessage && $latestClientMessage['id'] > $lastMessageId) {
                    error_log("Admin poll: Found new client message ID {$latestClientMessage['id']} that wasn't in regular results");
                    $messages[] = $latestClientMessage;
                }
                
                // If still no messages found with a zero lastMessageId, get the last 10 messages to ensure admin sees something
                if (empty($messages) && $lastMessageId === 0) {
                    // Get the last 10 messages to ensure admin sees something
                    $messages = getRows(
                        "SELECT * FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 10",
                        [$conversationId],
                        "i"
                    );
                    error_log("Admin poll: Zero lastMessageId, retrieving last " . count($messages) . " messages");
                    // Reverse to get chronological order
                    $messages = array_reverse($messages);
                }
            }
        }
        
        // Double-check for any client messages in the last 60 seconds that might be missed
        $recentClientMessages = getRows(
            "SELECT * FROM messages WHERE conversation_id = ? AND sender_type = 'client' AND sent_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)",
            [$conversationId],
            "i"
        );
        
        if (!empty($recentClientMessages)) {
            error_log("Admin poll: Found " . count($recentClientMessages) . " recent client messages in the last 60 seconds");
            
            // Add any recent client messages that aren't already in our messages array
            foreach ($recentClientMessages as $recentMessage) {
                $found = false;
                foreach ($messages as $message) {
                    if ($message['id'] == $recentMessage['id']) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    error_log("Admin poll: Adding missed recent client message ID {$recentMessage['id']}");
                    $messages[] = $recentMessage;
                }
            }
        }
        
        // Get client name
        $client = getRow(
            "SELECT username FROM users WHERE id = ?",
            [$conversation['client_id']],
            "i"
        );
        
        $clientName = $client ? $client['username'] : 'Client';
        
        // Format messages for the frontend
        $formattedMessages = [];
        foreach ($messages as $message) {
            $senderName = $message['sender_type'] === 'bot' ? 'Bus Rental Bot' : 
                       ($message['sender_type'] === 'admin' ? 'You (Agent)' : $clientName);
                       
            $formattedMessages[] = [
                'id' => $message['id'],
                'content' => $message['message'],
                'sender_type' => $message['sender_type'],
                'sender_name' => $senderName,
                'sent_at' => $message['sent_at']
            ];
            error_log("Admin poll: Formatting message ID " . $message['id'] . " from " . $message['sender_type']);
        }
        
        sendJsonResponse([
            'success' => true,
            'messages' => $formattedMessages,
            'status' => $conversation['status']
        ]);
        break;
        
    case 'close_conversation':
        // Check for required parameters
        if (!isset($_POST['conversation_id'])) {
            sendJsonResponse(['success' => false, 'message' => 'Missing conversation ID'], 400);
        }
        
        $conversationId = $_POST['conversation_id'];
        $closingMessage = isset($_POST['closing_message']) && !empty($_POST['closing_message']) 
            ? $_POST['closing_message'] 
            : "This conversation has been closed by the customer service agent. If you have additional questions, you can start a new conversation.";
        
        // Get conversation details to check permission
        $conversation = getRow(
            "SELECT * FROM conversations WHERE id = ?",
            [$conversationId],
            "i"
        );
        
        if (!$conversation) {
            sendJsonResponse(['success' => false, 'message' => 'Conversation not found'], 404);
        }
        
        // Only allow if this admin is assigned to this conversation
        if ($conversation['admin_id'] != $currentUser['id'] && $conversation['status'] !== 'closed') {
            sendJsonResponse(['success' => false, 'message' => 'Not authorized to close this conversation'], 403);
        }
        
        // Close the conversation
        closeConversation($conversationId);
        
        // Add system message with custom closing message
        $messageId = addMessage($conversationId, 'bot', $closingMessage);
        
        sendJsonResponse([
            'success' => true,
            'status' => 'closed',
            'closing_message' => [
                'id' => $messageId,
                'content' => $closingMessage,
                'sender_type' => 'bot',
                'sender_name' => 'System'
            ]
        ]);
        break;
        
    default:
        sendJsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
} 