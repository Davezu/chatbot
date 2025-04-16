<?php
/**
 * Ajax handler for chat functionality
 * This file processes ajax requests for the chat system
 */

// Include required files
require_once 'chatbot.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Make sure the request is an Ajax request
header('Content-Type: application/json');

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

// Handle different actions
switch ($action) {
    case 'send_message':
        // Check for required parameters
        if (!isset($_POST['message']) || !isset($_SESSION['conversation_id'])) {
            sendJsonResponse(['success' => false, 'message' => 'Missing parameters'], 400);
        }
        
        $message = $_POST['message'];
        $conversationId = $_SESSION['conversation_id'];
        
        // Add client message to conversation
        $messageId = addMessage($conversationId, 'client', $message);
        
        // Get the latest conversation status 
        $conversationStatus = getConversationStatus($conversationId);
        
        $response = [
            'success' => true,
            'message_id' => $messageId,
            'status' => $conversationStatus,
            'responses' => []
        ];
        
        // Only process with bot if not talking to a human
        if ($conversationStatus !== 'human_assigned') {
            // Process with bot
            $botResponse = processBotMessage($message, $conversationId);
            
            if ($botResponse !== null) {
                // Bot can handle the question
                $botMessageId = addMessage($conversationId, 'bot', $botResponse);
                $response['responses'][] = [
                    'id' => $botMessageId,
                    'content' => $botResponse,
                    'sender_type' => 'bot',
                    'sender_name' => 'Bus Rental Bot'
                ];
            } else {
                // Bot cannot handle the question, provide option to connect with a human agent
                $complexMessage = "I'm sorry, but I don't have enough information to answer your question properly. Would you like to talk to a customer service representative who can help you better?";
                
                // Add the complex message with buttons
                $complexMessageWithButtons = $complexMessage . ' <div class="mt-2"><button onclick="requestHumanAssistance()" class="btn btn-sm btn-primary">Yes, connect me with an agent</button> <button onclick="resetChat()" class="btn btn-sm btn-outline-secondary">No, I\'ll ask something else</button></div>';
                
                $botMessageId = addMessage($conversationId, 'bot', $complexMessageWithButtons);
                $response['responses'][] = [
                    'id' => $botMessageId,
                    'content' => $complexMessageWithButtons,
                    'sender_type' => 'bot',
                    'sender_name' => 'Bus Rental Bot'
                ];
            }
        }
        
        sendJsonResponse($response);
        break;
        
    case 'request_human':
        // Check for required parameters
        if (!isset($_SESSION['conversation_id'])) {
            sendJsonResponse(['success' => false, 'message' => 'Missing conversation ID'], 400);
        }
        
        $conversationId = $_SESSION['conversation_id'];
        $problemDescription = isset($_POST['problem']) ? $_POST['problem'] : '';
        
        // Add the user's question/problem as a client message if provided
        if (!empty($problemDescription)) {
            addMessage($conversationId, 'client', $problemDescription);
        }
        
        // Update conversation status to human_requested for admin attention
        requestHumanAssistance($conversationId);
        
        // Add a message explaining that an admin will be connected
        $adminMessage = "Thank you for your patience. I'm connecting you with one of our customer service representatives who will be able to help you better. Please wait a moment while I transfer your conversation to an available agent. They'll join the chat as soon as possible.";
        $messageId = addMessage($conversationId, 'bot', $adminMessage);
        
        sendJsonResponse([
            'success' => true,
            'status' => 'human_requested',
            'message' => [
                'id' => $messageId,
                'content' => $adminMessage,
                'sender_type' => 'bot',
                'sender_name' => 'Bus Rental Bot'
            ]
        ]);
        break;
        
    case 'get_messages':
        // Check for required parameters
        if (!isset($_SESSION['conversation_id'])) {
            sendJsonResponse(['success' => false, 'message' => 'Missing conversation ID'], 400);
        }
        
        $conversationId = $_SESSION['conversation_id'];
        $lastMessageId = isset($_POST['last_message_id']) ? (int)$_POST['last_message_id'] : 0;
        
        // Get conversation status
        $conversationStatus = getConversationStatus($conversationId);
        
        // Get new messages since last_message_id
        $messages = getNewMessages($conversationId, $lastMessageId);
        
        // Format messages for the frontend
        $formattedMessages = [];
        foreach ($messages as $message) {
            // Skip certain bot messages when human assigned
            if ($conversationStatus === 'human_assigned' && $message['sender_type'] === 'bot') {
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
            
            $senderName = $message['sender_type'] === 'bot' ? 'Bus Rental Bot' : 'You';
            if ($message['sender_type'] === 'admin') {
                $senderName = 'Customer Service';
            }
            
            $formattedMessages[] = [
                'id' => $message['id'],
                'content' => $message['message'],
                'sender_type' => $message['sender_type'],
                'sender_name' => $senderName,
                'sent_at' => $message['sent_at']
            ];
        }
        
        sendJsonResponse([
            'success' => true,
            'messages' => $formattedMessages,
            'status' => $conversationStatus
        ]);
        break;
        
    case 'quick_question':
        // Check for required parameters
        if (!isset($_POST['question']) || !isset($_SESSION['conversation_id'])) {
            sendJsonResponse(['success' => false, 'message' => 'Missing parameters'], 400);
        }
        
        $question = $_POST['question'];
        $conversationId = $_SESSION['conversation_id'];
        
        // Add client message to conversation
        $messageId = addMessage($conversationId, 'client', $question);
        
        // Process with bot
        $botResponse = processBotMessage($question, $conversationId);
        
        if ($botResponse === null) {
            // Use a default response for quick questions if bot doesn't have an answer
            $botResponse = "I'm not sure I have all the information you need about that. Would you like to provide more details or connect with a customer service representative?";
        }
        
        $botMessageId = addMessage($conversationId, 'bot', $botResponse);
        
        sendJsonResponse([
            'success' => true,
            'client_message' => [
                'id' => $messageId,
                'content' => $question,
                'sender_type' => 'client',
                'sender_name' => 'You'
            ],
            'bot_message' => [
                'id' => $botMessageId,
                'content' => $botResponse,
                'sender_type' => 'bot',
                'sender_name' => 'Bus Rental Bot'
            ],
            'status' => getConversationStatus($conversationId)
        ]);
        break;
        
    default:
        sendJsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

/**
 * Get new messages since the specified message ID
 */
function getNewMessages($conversationId, $lastMessageId = 0) {
    return getRows(
        "SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY sent_at ASC",
        [$conversationId, $lastMessageId],
        "ii"
    );
} 