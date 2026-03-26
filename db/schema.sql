CREATE DATABASE IF NOT EXISTS medicare;
USE medicare;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS uploads;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient','doctor','admin') NOT NULL DEFAULT 'patient',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    dob DATE,
    blood_type VARCHAR(5),
    allergies TEXT,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    datetime DATETIME NOT NULL,
    status ENUM('scheduled','completed','cancelled') DEFAULT 'scheduled',
    notes TEXT,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    body TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    ip VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (name, email, password, role) VALUES
('Alice Johnson', 'alice@demo.com', '482c811da5d5b4bc6d497ffa98491e38', 'patient'),
('Bob Williams', 'bob@demo.com', '482c811da5d5b4bc6d497ffa98491e38', 'patient'),
('Dr. Sarah Smith', 'drsmith@demo.com', '482c811da5d5b4bc6d497ffa98491e38', 'doctor'),
('Admin User', 'admin@demo.com', '0192023a7bbd73250516f069df18b500', 'admin');

INSERT INTO patients (user_id, dob, blood_type, allergies, notes) VALUES
(1, '1990-03-15', 'A+', 'Penicillin', 'Regular checkup patient. History of seasonal allergies.'),
(2, '1985-07-22', 'O-', 'None', 'Pre-diabetic. Monitor blood sugar quarterly.');

INSERT INTO appointments (patient_id, doctor_id, datetime, status, notes) VALUES
(1, 3, '2026-04-01 09:00:00', 'scheduled', 'Annual physical exam'),
(1, 3, '2026-03-10 14:00:00', 'completed', 'Follow-up on blood work'),
(2, 3, '2026-04-05 11:00:00', 'scheduled', 'Blood sugar review');

INSERT INTO messages (sender_id, receiver_id, body) VALUES
(1, 3, 'Hi Dr. Smith, I have been experiencing headaches lately.'),
(3, 1, 'Hello Alice, how long have these headaches been occurring?'),
(1, 3, 'About two weeks now, mostly in the afternoon.'),
(2, 3, 'Dr. Smith, my blood sugar reading was 140 this morning.');
