# Bus Rental Hybrid Chatbot System

A web-based hybrid chatbot system for a bus rental service with both automated bot responses and human agent support.

## Features

- **Client-side Chat Interface**

  - User registration and login
  - Automated chatbot for common questions
  - Option to request human assistance for complex queries
  - Real-time chat experience

- **Admin Panel**

  - Dashboard for managing conversations
  - View conversations requiring human assistance
  - Accept and handle customer conversations
  - Close conversations when resolved

- **Hybrid Support System**
  - Bot handles common questions automatically
  - Detects complex queries and offers human support
  - Seamless transition from bot to human agent
  - Predefined responses for quick replies

## Installation

1. **Database Setup**

   - Create a MySQL database
   - Import the `database.sql` file to set up the tables and initial data
   - Default admin credentials: Username: `admin`, Password: `admin123`

2. **Server Configuration**

   - Place all files in your web server directory (e.g., htdocs for XAMPP)
   - Edit `config/database.php` with your database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'bus_rental_chatbot');
     ```

3. **Folder Structure**
   - Ensure the following folder structure:
     ```
     /
     ├── config/
     │   └── database.php
     ├── includes/
     │   ├── auth.php
     │   └── chatbot.php
     ├── index.php
     ├── admin.php
     ├── admin_chat.php
     ├── logout.php
     ├── database.sql
     └── README.md
     ```

## Usage

1. **Client Side**

   - Open `index.php` in a browser
   - Register a new account or login with existing credentials
   - Start chatting with the bot
   - For complex questions, request human assistance

2. **Admin Side**
   - Login with admin credentials
   - Navigate to the Admin Panel
   - View and accept conversations requiring assistance
   - Communicate with clients
   - Use quick replies for common responses
   - Close conversations when resolved

## System Requirements

- PHP 7.3 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)
- Modern web browser with JavaScript enabled

## Customization

- **Adding Bot Responses**

  - Insert new entries in the `bot_responses` table
  - Customize the `processBotMessage()` function in `includes/chatbot.php`

- **UI Customization**
  - Modify CSS styles in the respective files
  - Update Bootstrap components as needed

## Security Considerations

- All user passwords are hashed for security
- Input validation is implemented
- Session management for authentication
- Prepared statements for database queries to prevent SQL injection

## Future Enhancements

- Implement real-time messaging using WebSockets
- Add advanced NLP for better query understanding
- Implement conversation ratings and feedback
- Add support for file uploads and sharing
- Create mobile-responsive admin interface

## License

This project is available for private use. Customization and redistribution require permission.

## Support

For support or customization, please contact the developer.
