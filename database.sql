-- Barangay Konek Database Schema 
CREATE DATABASE IF NOT EXISTS barangay_konek; USE barangay_konek; 
-- Drop existing tables (safe to re-run) 
SET FOREIGN_KEY_CHECKS = 0; 
DROP TABLE IF EXISTS complaint_attachment; 
DROP TABLE IF EXISTS complaint; DROP TABLE IF EXISTS complaint_status; 
DROP TABLE IF EXISTS requested_document; DROP TABLE IF EXISTS request; 
DROP TABLE IF EXISTS request_status; DROP TABLE IF EXISTS document_type; 
DROP TABLE IF EXISTS document_requirement;
DROP TABLE IF EXISTS user_verification; DROP TABLE IF EXISTS verification_status; 
DROP TABLE IF EXISTS announcement; 
DROP TABLE IF EXISTS barangay; 
DROP TABLE IF EXISTS city; 
DROP TABLE IF EXISTS province; 
DROP TABLE IF EXISTS profile; 
DROP TABLE IF EXISTS sex; 
DROP TABLE IF EXISTS users; 
DROP TABLE IF EXISTS usertype; 
SET FOREIGN_KEY_CHECKS = 1; 

-- usertype table / user roles 
CREATE TABLE usertype (
    id int AUTO_INCREMENT PRIMARY KEY, 
    name varchar(50) UNIQUE NOT NULL 
); 
INSERT INTO usertype (name) VALUES ('superadmin'), ('admin'), ('staff'), ('user'); 

-- User table 
CREATE TABLE users (
    id int PRIMARY KEY AUTO_INCREMENT,
    username varchar(100) UNIQUE NOT NULL,
    password_hash varchar(255) NOT NULL, 
    usertype_id int NOT NULL, 
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, 
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
    deleted_at TIMESTAMP NULL DEFAULT NULL, 
    FOREIGN KEY (usertype_id) REFERENCES usertype(id) ON DELETE CASCADE ON UPDATE CASCADE 
); 

-- province / city / barangay hierarchy 
CREATE TABLE province (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL 
); 

CREATE TABLE city (
    id INT PRIMARY KEY AUTO_INCREMENT, 
    name VARCHAR(255) NOT NULL, 
    province_id INT NOT NULL, 
    FOREIGN KEY (province_id) REFERENCES province(id) ON DELETE CASCADE ON UPDATE CASCADE 
); 

CREATE TABLE barangay ( 
    id INT PRIMARY KEY AUTO_INCREMENT, 
    name VARCHAR(255) NOT NULL, 
    city_id INT NOT NULL, 
    address_details TEXT NULL, 
    contact_number VARCHAR(25) NULL, 
    email VARCHAR(255) NULL, 
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, 
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
    deleted_at TIMESTAMP NULL DEFAULT NULL, 
    FOREIGN KEY (city_id) REFERENCES city(id) ON DELETE CASCADE ON UPDATE CASCADE
); 

-- sex table 
CREATE TABLE sex (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    name VARCHAR(50) 
); 
INSERT INTO sex (name) VALUES ('Male'), ('Female'); 

-- profile table (one-to-one with user) 
CREATE TABLE profile ( 
    id int PRIMARY KEY AUTO_INCREMENT, 
    last_name varchar(255), 
    first_name varchar(255), 
    middle_name varchar(255),
    suffix varchar(50), 
    sex_id int, 
    email varchar(255) UNIQUE, 
    contact_number varchar(25), 
    birthdate date, user_id int UNIQUE, 
    barangay_id INT, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
    FOREIGN KEY (sex_id) REFERENCES sex(id) ON DELETE SET NULL ON UPDATE CASCADE, 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE, 
    FOREIGN KEY (barangay_id) REFERENCES barangay(id) ON DELETE SET NULL ON UPDATE CASCADE 
); 
-- seeding of user 
INSERT INTO users (username, password_hash, usertype_id) VALUES ('sample_superadmin', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', 1); 
INSERT INTO users (username, password_hash, usertype_id) VALUES ('sample_admin', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', 2); 
INSERT INTO users (username, password_hash, usertype_id) VALUES ('sample_staff', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', 3); 
INSERT INTO users (username, password_hash, usertype_id) VALUES ('sample_user', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', 4); 

-- seeding of profiles 
INSERT INTO profile (last_name, first_name, suffix, sex_id, email, contact_number, birthdate, user_id, barangay_id) VALUES ('Doe', 'SA John', '', 1,'superadmin@gmail.com', '0912-345-6789', '2004-05-17', 1, 1); 
INSERT INTO profile (last_name, first_name, suffix, sex_id, email, contact_number, birthdate, user_id, barangay_id) VALUES ('Doe', 'Ad John', 'III', 2,'admin@gmail.com', '0911-345-6789', '2006-11-11', 2, 1); 
INSERT INTO profile (last_name, first_name, suffix, sex_id, email, contact_number, birthdate, user_id, barangay_id) VALUES ('Doe', 'St John', '', 2,'staff@gmail.com', '0913-345-6789', '2002-04-12', 3, 1); 
INSERT INTO profile (last_name, first_name, suffix, sex_id, email, contact_number, birthdate, user_id, barangay_id) VALUES ('Doe', 'Us John', 'Jr.', 1,'user@gmail.com', '0914-345-6789', '1992-04-11', 4, 1); 

-- announcement 
CREATE TABLE announcement (
    id INT PRIMARY KEY AUTO_INCREMENT, 
    title VARCHAR(255) NOT NULL, 
    content TEXT, 
    image_path VARCHAR(255), 
    user_id INT NULL, 
    barangay_id INT, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    deleted_at TIMESTAMP NULL, 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE, 
    FOREIGN KEY (barangay_id) REFERENCES barangay(id) ON DELETE CASCADE ON UPDATE CASCADE ); 
    
-- verification status and resident verification 
CREATE TABLE verification_status (
    id INT PRIMARY KEY AUTO_INCREMENT, 
    name VARCHAR(255) NOT NULL 
); 
INSERT INTO verification_status (name) VALUES ('pending'), ('verified'), ('rejected'); 

CREATE TABLE user_verification ( 
    id INT PRIMARY KEY AUTO_INCREMENT, 
    user_id INT NOT NULL UNIQUE, 
    profile_id INT NOT NULL, 
    filename VARCHAR(255), 
    file_path VARCHAR(255), 
    remarks VARCHAR(255), 
    verification_status_id INT default 1, 
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
    verified_by INT NULL, verified_at DATETIME, 
    FOREIGN KEY (profile_id) REFERENCES profile(id) ON DELETE CASCADE ON UPDATE CASCADE, 
    FOREIGN KEY (verification_status_id) REFERENCES verification_status(id) ON DELETE CASCADE ON UPDATE CASCADE, 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE, 
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE 
); 

-- document types and request status 
CREATE TABLE document_type ( 
    id INT PRIMARY KEY AUTO_INCREMENT, 
    name VARCHAR(255) NOT NULL, 
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
); 

CREATE TABLE document_form (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    form_type VARCHAR(50) DEFAULT 'custom',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE document_requirement (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_type_id INT NOT NULL,
    requirement_type ENUM('document_upload', 'text_input', 'form') NOT NULL,
    label VARCHAR(255) NOT NULL,
    description TEXT,
    field_type VARCHAR(50),
    is_required BOOLEAN DEFAULT TRUE,
    validation_rules JSON,
    form_id INT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_type_id) REFERENCES document_type(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (form_id) REFERENCES document_form(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE document_requirement_submission (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    requirement_id INT NOT NULL,
    submission_type VARCHAR(50) NOT NULL,
    text_value TEXT,
    file_path VARCHAR(255),
    file_name VARCHAR(255),
    file_type VARCHAR(100),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES request(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (requirement_id) REFERENCES document_requirement(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE request_status (
    id INT PRIMARY KEY AUTO_INCREMENT, 
    name VARCHAR(100) NOT NULL 
); 
INSERT INTO request_status (name) VALUES ('pending'), ('approved'), ('rejected'), ('completed'); 

-- request table 
CREATE TABLE request ( 
    id INT PRIMARY KEY AUTO_INCREMENT, 
    user_id INT NOT NULL, 
    document_type_id INT NOT NULL, 
    remarks VARCHAR(255), 
    request_status_id INT DEFAULT 1, 
    claimed_by INT NULL, 
    barangay_id INT NULL, 
    document_path VARCHAR(255) NULL, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
    deleted_at DATETIME NULL, 
    FOREIGN KEY (claimed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE, 
    FOREIGN KEY (barangay_id) REFERENCES barangay(id) ON DELETE CASCADE ON UPDATE CASCADE, 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE, 
    FOREIGN KEY (document_type_id) REFERENCES document_type(id) ON DELETE CASCADE ON UPDATE CASCADE, 
    FOREIGN KEY (request_status_id) REFERENCES request_status(id) ON DELETE CASCADE ON UPDATE CASCADE 
); 

-- requested_document (attachments per request) 
CREATE TABLE requested_document (
    id INT PRIMARY KEY AUTO_INCREMENT, 
    name VARCHAR(255), 
    request_id INT, 
    file_path VARCHAR(255), 
    file_type VARCHAR(100), 
    uploaded_at DATETIME, 
    deleted_at DATETIME NULL, 
    FOREIGN KEY (request_id) REFERENCES request(id) ON DELETE CASCADE ON UPDATE CASCADE 
); 

-- complaint and attachments 
CREATE TABLE complaint_status (
    id INT PRIMARY KEY AUTO_INCREMENT, 
    name VARCHAR(100) NOT NULL 
); 
INSERT INTO complaint_status (name) VALUES ('open'), ('in_progress'), ('resolved'), ('closed'); 

CREATE TABLE complaint ( 
    id INT PRIMARY KEY AUTO_INCREMENT, 
    user_id INT NOT NULL, 
    title VARCHAR(255), 
    description TEXT, 
    complaint_status_id INT, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
    deleted_at DATETIME NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE, 
    FOREIGN KEY (complaint_status_id) REFERENCES complaint_status(id) ON DELETE CASCADE ON UPDATE CASCADE 
); 

CREATE TABLE complaint_attachment ( 
    id INT PRIMARY KEY AUTO_INCREMENT, 
    complaint_id INT, file_path VARCHAR(255), 
    uploaded_at DATETIME, 
    FOREIGN KEY (complaint_id) REFERENCES complaint(id) ON DELETE CASCADE ON UPDATE CASCADE 
); 

CREATE TABLE notification ( 
    id INT PRIMARY KEY AUTO_INCREMENT, 
    user_id INT NOT NULL, 
    title VARCHAR(255), 
    message TEXT, 
    is_read BOOLEAN DEFAULT FALSE, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE 
); 

CREATE TABLE activity_log ( id INT PRIMARY KEY AUTO_INCREMENT, 
    user_id INT NULL, action VARCHAR(255), reference_table VARCHAR(100), 
    reference_id INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL 
);