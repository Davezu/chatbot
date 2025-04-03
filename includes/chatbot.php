<?php
// Include database configuration - fix the path to properly reference from the root directory
require_once __DIR__ . '/../config/database.php';

/**
 * Analyze message to determine if it can be answered by the bot
 * Returns either a bot response or NULL if human assistance is needed
 */
function processBotMessage($message, $conversationId = null) {
    // Convert message to lowercase for easier matching
    $message = strtolower($message);
    
    // Check for keywords in predefined responses
    $botResponses = getRows("SELECT * FROM bot_responses");
    
    foreach ($botResponses as $response) {
        $keyword = strtolower($response['keyword']);
        if (strpos($message, $keyword) !== false) {
            return $response['response'];
        }
    }
    
    // Simple keyword matching for common bus rental questions
    if (strpos($message, 'price') !== false || strpos($message, 'cost') !== false || strpos($message, 'rate') !== false) {
        return getBotResponse('pricing');
    } elseif (strpos($message, 'book') !== false || strpos($message, 'reserve') !== false || strpos($message, 'schedule') !== false) {
        return getBotResponse('booking');
    } elseif (strpos($message, 'cancel') !== false || strpos($message, 'refund') !== false) {
        return getBotResponse('cancellation');
    } elseif (strpos($message, 'contact') !== false || strpos($message, 'phone') !== false || strpos($message, 'email') !== false || strpos($message, 'reach') !== false) {
        return getBotResponse('contact');
    } elseif (strpos($message, 'bus') !== false || strpos($message, 'vehicle') !== false || strpos($message, 'coach') !== false || strpos($message, 'fleet') !== false) {
        return getBotResponse('fleet');
    } elseif (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false || strpos($message, 'hey') !== false) {
        return "Hello! How can I assist you with our bus rental services today?";
    } elseif (strpos($message, 'thanks') !== false || strpos($message, 'thank you') !== false) {
        return "You're welcome! Is there anything else I can help you with regarding our bus rental services?";
    }
    
    // Check message complexity to determine if human assistance is needed
    if (isComplexQuery($message)) {
        return null; // Indicate that human assistance is needed
    }
    
    // If no match is found, return null to trigger the human connection option
    return null;
}

/**
 * Get a bot response by keyword
 */
function getBotResponse($keyword) {
    $response = getRow("SELECT response FROM bot_responses WHERE keyword = ?", [$keyword], "s");
    return $response ? $response['response'] : null;
}

/**
 * Determine if the query is complex and needs human assistance
 */
function isComplexQuery($message) {
    // Count the number of words in the message
    $wordCount = str_word_count($message);
    
    // Check for question complexity indicators
    $complexIndicators = [
        'how can i', 'what is the best', 'custom', 'special', 'specific route',
        'multiple stops', 'discount', 'negotiate', 'compare', 'difference between',
        'emergency', 'accident', 'breakdown', 'complaint', 'problem', 'issue',
        'wheelchair', 'accessible', 'accommodation', 'medical', 'assistance',
        'modify', 'change my', 'reschedule', 'group rate'
    ];
    
    foreach ($complexIndicators as $indicator) {
        if (strpos($message, $indicator) !== false) {
            return true;
        }
    }
    
    // If the message is very long, it's likely complex
    if ($wordCount > 25) {
        return true;
    }
    
    return false;
}

/**
 * Create a new conversation
 */
function createConversation($clientId) {
    $conversationId = insertData(
        "INSERT INTO conversations (client_id, status) VALUES (?, 'bot')",
        [$clientId],
        "i"
    );
    
    return $conversationId;
}

/**
 * Add a message to a conversation
 */
function addMessage($conversationId, $senderType, $message) {
    $messageId = insertData(
        "INSERT INTO messages (conversation_id, sender_type, message) VALUES (?, ?, ?)",
        [$conversationId, $senderType, $message],
        "iss"
    );
    
    // Update the conversation's updated_at timestamp
    executeQuery(
        "UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        [$conversationId],
        "i"
    );
    
    return $messageId;
}

/**
 * Request human assistance for a conversation
 */
function requestHumanAssistance($conversationId) {
    executeQuery(
        "UPDATE conversations SET status = 'human_requested' WHERE id = ?",
        [$conversationId],
        "i"
    );
}

/**
 * Assign an admin to a conversation
 */
function assignAdminToConversation($conversationId, $adminId) {
    executeQuery(
        "UPDATE conversations SET status = 'human_assigned', admin_id = ? WHERE id = ?",
        [$adminId, $conversationId],
        "ii"
    );
}

/**
 * Close a conversation
 */
function closeConversation($conversationId) {
    executeQuery(
        "UPDATE conversations SET status = 'closed' WHERE id = ?",
        [$conversationId],
        "i"
    );
}

/**
 * Get all messages for a conversation
 */
function getConversationMessages($conversationId) {
    return getRows(
        "SELECT * FROM messages WHERE conversation_id = ? ORDER BY sent_at ASC",
        [$conversationId],
        "i"
    );
}

/**
 * Get conversation status
 */
function getConversationStatus($conversationId) {
    $conversation = getRow(
        "SELECT status FROM conversations WHERE id = ?",
        [$conversationId],
        "i"
    );
    
    return $conversation ? $conversation['status'] : null;
}
?> 