-- Database schema for the Hybrid ChatBot

-- Create database
CREATE DATABASE IF NOT EXISTS bus_rental_chatbot;
USE bus_rental_chatbot;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, password, email, role) VALUES
('admin', '$2y$10$qnfxqYH9RUXXnuqTRRXcH.iP4K5u.Ytc23ZG3aZuFeTK9RG2K1o/K', 'admin@busrental.com', 'admin');
-- Default password is 'admin123' (hashed)

-- Create conversations table
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    status ENUM('bot', 'human_requested', 'human_assigned', 'closed') NOT NULL DEFAULT 'bot',
    admin_id INT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

-- Create messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_type ENUM('bot', 'client', 'admin') NOT NULL,
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
);

-- Create bot_responses table for predefined answers
CREATE TABLE IF NOT EXISTS bot_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    keyword VARCHAR(255) NOT NULL,
    response TEXT NOT NULL
);

-- Insert some default predefined bot responses
INSERT INTO bot_responses (keyword, response) VALUES
('pricing', 'Our bus rental pricing starts at $100/hour for standard buses and $150/hour for luxury coaches. The minimum booking time is 4 hours. For a detailed quote, please provide your trip details.'),
('booking', 'To book a bus, please provide the following information: date, time, pickup location, destination, number of passengers, and any special requirements. We''ll then prepare a quote for you.'),
('cancellation', 'Our cancellation policy allows free cancellation up to 48 hours before the scheduled trip. Cancellations within 48 hours will incur a 50% charge. No-shows are charged the full amount.'),
('contact', 'You can contact our customer service team at 1-800-BUS-RENT or email us at support@busrental.com. Our office hours are Monday to Friday, 9am to 5pm EST.'),
('fleet', 'We offer various types of buses including: Standard Coaches (up to 56 passengers), Mini Buses (up to 25 passengers), Executive Coaches (up to 40 passengers with premium amenities), and School Buses (up to 48 passengers).'); 