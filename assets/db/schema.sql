-- Bus Rental Chat Service Database Schema
-- Use this file to set up the database structure for the chatbot application

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS bus_rental_chatbot DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bus_rental_chatbot;

-- Users table for clients and admins
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create default admin user (username: admin, password: admin123)
INSERT INTO users (username, password, email, role) 
VALUES ('admin', '$2y$10$9Jxbv60GE7qEipALbm8x3u0MQ1jFKgDOaJCGpTfkWzQJZJ7.EwWcu', 'admin@example.com', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- Conversations table
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    admin_id INT DEFAULT NULL,
    status ENUM('bot', 'human_requested', 'human_assigned', 'closed') NOT NULL DEFAULT 'bot',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_type ENUM('bot', 'client', 'admin') NOT NULL,
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
);

-- Bot responses table
CREATE TABLE IF NOT EXISTS bot_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    keyword VARCHAR(100) NOT NULL UNIQUE,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default bot responses
INSERT INTO bot_responses (keyword, response) VALUES
('pricing', 'Our bus rental pricing starts at $500 for a standard bus for a day. For premium coaches, prices start at $800 per day. For longer trips or customized packages, we offer special discounts. Would you like a detailed quote based on your needs?'),
('booking', 'To book a bus, you can: 1) Call our reservation line at 1-800-BUS-RENT, 2) Fill out the booking form on our website, or 3) Email your requirements to bookings@busrental.com. We need details like date, time, number of passengers, pickup/dropoff locations, and any special requirements.'),
('cancellation', 'Our cancellation policy is as follows: Full refund if cancelled 14+ days before the reservation; 50% refund if cancelled 7-13 days before; 25% refund if cancelled 3-6 days before; No refund for cancellations less than 3 days before the scheduled date.'),
('contact', 'You can reach our customer service team by phone at 1-800-BUS-RENT (Monday-Friday, 9AM-5PM EST), by email at support@busrental.com, or through the contact form on our website. For emergencies during an ongoing rental, call our 24/7 support line at 1-888-BUS-HELP.'),
('fleet', 'We offer various types of buses: Standard buses (up to 50 passengers), Luxury coaches (up to 40 passengers with premium amenities), Mini buses (up to 25 passengers), and Shuttle vans (up to 15 passengers). All vehicles are regularly maintained and include professional drivers.')
ON DUPLICATE KEY UPDATE id=id; 