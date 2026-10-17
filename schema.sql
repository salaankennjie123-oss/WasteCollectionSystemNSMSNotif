CREATE DATABASE IF NOT EXISTS talusan_waste CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE talusan_waste;

CREATE TABLE IF NOT EXISTS admin (
  admin_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  position VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schedules (
  schedule_id INT AUTO_INCREMENT PRIMARY KEY,
  collection_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  notes VARCHAR(255) NOT NULL,
  status ENUM('Draft','Active','Done') NOT NULL DEFAULT 'Draft',
  created_by INT NOT NULL,
  coverage_area VARCHAR(255) NULL,
  FOREIGN KEY (created_by) REFERENCES admin(admin_id),
  INDEX idx_schedule_date (collection_date),
  INDEX idx_schedule_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS residents (
  resident_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  mobile_number VARCHAR(20) NOT NULL,
  sitio VARCHAR(100) NOT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL,
  INDEX idx_resident_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcements (
  announcement_id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('Draft','Published') NOT NULL DEFAULT 'Draft',
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES admin(admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_logs (
  sms_id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT NULL,
  announcement_id INT NULL,
  resident_id INT NOT NULL,
  message TEXT NOT NULL,
  status ENUM('sent','failed','queued') NOT NULL DEFAULT 'queued',
  sent_at DATETIME NULL,
  attempts INT NOT NULL DEFAULT 0,
  FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE CASCADE,
  FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
  FOREIGN KEY (resident_id) REFERENCES residents(resident_id),
  INDEX idx_sms_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schedule_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT NOT NULL,
  admin_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  details VARCHAR(255) NULL,
  logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES admin(admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_throttle (
  username VARCHAR(50) NOT NULL PRIMARY KEY,
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until INT UNSIGNED NOT NULL DEFAULT 0,
  last_ip VARCHAR(45) NULL,
  updated_at INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admin (full_name, position, username, password_hash)
SELECT 'Maria Santos', 'Barangay Secretary', 'secretary', '$2y$12$ijcJ4kLPW2wYvlHMpVmJEO.tF6h5wuSA4OlvHEnsdU5yDItTIK3oa'
WHERE NOT EXISTS (SELECT 1 FROM admin WHERE username = 'secretary');

INSERT INTO residents (name, mobile_number, sitio, is_deleted, deleted_at)
SELECT * FROM (
  SELECT 'Roberto Flores', '+639120000001', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Luz Garcia', '+639120000002', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Maria Valdez', '+639120000003', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Rey Ramos', '+639120000004', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Eduardo Rivera', '+639120000005', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Gloria Rivera', '+639120000006', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Kenneth Hernandez', '+639120000007', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Noel Reyes', '+639120000008', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Rey Valdez', '+639120000009', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Teresita Ocampo', '+639120000010', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Antonio Lopez', '+639120000011', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Cynthia Hernandez', '+639120000012', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Jose Torres', '+639120000013', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Jayson Villanueva', '+639120000014', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Ana Mendoza', '+639120000015', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Elena Castro', '+639120000016', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Danilo Lopez', '+639120000017', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Danilo Navarro', '+639120000018', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Jayson Soriano', '+639120000019', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Gloria Pascual', '+639120000020', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Liza Valdez', '+639120000021', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Jocelyn Salazar', '+639120000022', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Rey Flores', '+639120000023', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Elena Gonzales', '+639120000024', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Ana Soriano', '+639120000025', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Noel Pascual', '+639120000026', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Liza Salazar', '+639120000027', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Roberto Aquino', '+639120000028', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Kenneth Mercado', '+639120000029', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Juan Manalo', '+639120000030', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Rosario Agustin', '+639120000031', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Jun Rivera', '+639120000032', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Nenita Domingo', '+639120000033', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Jun Navarro', '+639120000034', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Luz Agustin', '+639120000035', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Rosario Ocampo', '+639120000036', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Josefina Gonzales', '+639120000037', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Rey Navarro', '+639120000038', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Jocelyn Dela Cruz', '+639120000039', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Liza Del Rosario', '+639120000040', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Maricel Rivera', '+639120000041', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Gloria Villanueva', '+639120000042', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Rosario Reyes', '+639120000043', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Eduardo Hernandez', '+639120000044', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Roberto Tolentino', '+639120000045', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Maria Pascual', '+639120000046', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Noel Valdez', '+639120000047', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Josefina Mendoza', '+639120000048', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Liza Soriano', '+639120000049', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Maria Dela Cruz', '+639120000050', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Pedro Fernandez', '+639120000051', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Lorna Valdez', '+639120000052', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Analyn Villanueva', '+639120000053', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Liza Aquino', '+639120000054', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Luz Rivera', '+639120000055', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Arnel Torres', '+639120000056', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Eduardo Tolentino', '+639120000057', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Juan Castro', '+639120000058', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Arnel Flores', '+639120000059', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Danilo Ramos', '+639120000060', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Luz Santos', '+639120000061', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Shiela Aquino', '+639120000062', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Jocelyn Garcia', '+639120000063', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Jose Fernandez', '+639120000064', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Imelda Dela Cruz', '+639120000065', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Alfredo Torres', '+639120000066', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Rodolfo Santos', '+639120000067', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Rosario Tolentino', '+639120000068', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Cynthia Castro', '+639120000069', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Carmela Mendoza', '+639120000070', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Cynthia Gonzales', '+639120000071', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Maricel Bautista', '+639120000072', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Ricardo Navarro', '+639120000073', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Maria Aquino', '+639120000074', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Analyn Aquino', '+639120000075', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Rey Torres', '+639120000076', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Armando Garcia', '+639120000077', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Danilo Agustin', '+639120000078', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Mark Hernandez', '+639120000079', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Gloria Lopez', '+639120000080', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Cesar Mercado', '+639120000081', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Mark Santos', '+639120000082', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Carlos Bautista', '+639120000083', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Mylene Mercado', '+639120000084', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Roberto Gonzales', '+639120000085', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Shiela Valdez', '+639120000086', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Corazon Pascual', '+639120000087', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Shiela Tolentino', '+639120000088', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Eduardo Garcia', '+639120000089', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Teresita Hernandez', '+639120000090', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Ana Valdez', '+639120000091', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Jayson Salazar', '+639120000092', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Rodolfo Rivera', '+639120000093', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Maricel Mercado', '+639120000094', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Luz Lopez', '+639120000095', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Jocelyn Manalo', '+639120000096', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Rodolfo Mercado', '+639120000097', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Jose Hernandez', '+639120000098', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Analyn Agustin', '+639120000099', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Rowena Del Rosario', '+639120000100', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Cynthia Navarro', '+639120000101', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Vicente Domingo', '+639120000102', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Carmela Torres', '+639120000103', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Analyn Torres', '+639120000104', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Armando Soriano', '+639120000105', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Lorna Domingo', '+639120000106', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Danilo Manalo', '+639120000107', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Ana Gonzales', '+639120000108', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Kenneth Del Rosario', '+639120000109', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Mark Castro', '+639120000110', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Lorna Pascual', '+639120000111', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Kenneth Lopez', '+639120000112', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Liza Castro', '+639120000113', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Ana Rivera', '+639120000114', 'Sitio Silangan', 0, NULL UNION ALL
  SELECT 'Efren Castro', '+639120000115', 'Sitio Mabuhay', 0, NULL UNION ALL
  SELECT 'Ramon Pascual', '+639120000116', 'Sitio Bagong Silang', 0, NULL UNION ALL
  SELECT 'Antonio Hernandez', '+639120000117', 'Sitio Maligaya', 0, NULL UNION ALL
  SELECT 'Shiela Lopez', '+639120000118', 'Sitio Masagana', 0, NULL UNION ALL
  SELECT 'Corazon Mendoza', '+639120000119', 'Sitio Paraiso', 0, NULL UNION ALL
  SELECT 'Liza Garcia', '+639120000120', 'Sitio Sampaguita', 0, NULL UNION ALL
  SELECT 'Nenita Flores', '+639120000121', 'Sitio Centro', 0, NULL UNION ALL
  SELECT 'Rodolfo Soriano', '+639120000122', 'Sitio Riverside', 0, NULL UNION ALL
  SELECT 'Corazon Mercado', '+639120000123', 'Sitio Kanluran', 0, NULL UNION ALL
  SELECT 'Efren Cruz', '+639120000124', 'Sitio Silangan', 1, '2026-10-05 09:00:00' UNION ALL
  SELECT 'Ricardo Rivera', '+639120000125', 'Sitio Mabuhay', 1, '2026-10-05 09:00:00'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM residents);

INSERT INTO schedules (collection_date, start_time, end_time, notes, status, created_by, coverage_area)
SELECT '2026-10-02', '06:00:00', '10:00:00', 'Please place your waste outside before 6 AM.', 'Done', 1, 'All sitios'
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE collection_date = '2026-10-02')
UNION ALL
SELECT '2026-10-09', '06:00:00', '10:00:00', 'Please place your waste outside before 6 AM.', 'Active', 1, 'All sitios'
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE collection_date = '2026-10-09')
UNION ALL
SELECT '2026-10-13', '07:00:00', '11:00:00', 'General waste collection.', 'Active', 1, 'Sitio Centro, Sitio Riverside, Sitio Kanluran'
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE collection_date = '2026-10-13')
UNION ALL
SELECT '2026-10-16', '06:00:00', '10:00:00', 'Segregate recyclables from other waste.', 'Draft', 1, 'Sitio Silangan, Sitio Mabuhay, Sitio Bagong Silang'
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE collection_date = '2026-10-16')
UNION ALL
SELECT '2026-10-20', '06:00:00', '10:00:00', 'Monthly deep clean.', 'Active', 1, 'All sitios'
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE collection_date = '2026-10-20')
UNION ALL
SELECT '2026-10-27', '06:00:00', '10:00:00', 'Biodegradable waste only.', 'Draft', 1, 'Sitio Maligaya, Sitio Masagana, Sitio Paraiso, Sitio Sampaguita'
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE collection_date = '2026-10-27')
UNION ALL
SELECT '2026-11-03', '06:00:00', '10:00:00', 'General waste collection.', 'Draft', 1, 'All sitios'
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE collection_date = '2026-11-03');

INSERT INTO schedule_logs (schedule_id, admin_id, action, details, logged_at)
SELECT 1, 1, 'created', 'Schedule created for 2026-10-02 06:00-10:00', '2026-09-28 08:00:00'
WHERE NOT EXISTS (SELECT 1 FROM schedule_logs WHERE schedule_id = 1 AND action = 'created')
UNION ALL
SELECT 1, 1, 'published', 'Status set to Active', '2026-09-28 08:05:00'
WHERE NOT EXISTS (SELECT 1 FROM schedule_logs WHERE schedule_id = 1 AND action = 'published')
UNION ALL
SELECT 1, 1, 'updated', 'Status set to Done', '2026-10-02 12:00:00'
WHERE NOT EXISTS (SELECT 1 FROM schedule_logs WHERE schedule_id = 1 AND action = 'updated' AND logged_at = '2026-10-02 12:00:00')
UNION ALL
SELECT 2, 1, 'created', 'Schedule created for 2026-10-09 06:00-10:00', '2026-10-01 09:00:00'
WHERE NOT EXISTS (SELECT 1 FROM schedule_logs WHERE schedule_id = 2 AND action = 'created')
UNION ALL
SELECT 2, 1, 'published', 'Status set to Active', '2026-10-01 09:02:00'
WHERE NOT EXISTS (SELECT 1 FROM schedule_logs WHERE schedule_id = 2 AND action = 'published')
UNION ALL
SELECT 3, 1, 'created', 'Schedule created for 2026-10-13 07:00-11:00', '2026-10-01 09:10:00'
WHERE NOT EXISTS (SELECT 1 FROM schedule_logs WHERE schedule_id = 3 AND action = 'created')
UNION ALL
SELECT 3, 1, 'published', 'Status set to Active', '2026-10-01 09:12:00'
WHERE NOT EXISTS (SELECT 1 FROM schedule_logs WHERE schedule_id = 3 AND action = 'published')
UNION ALL
SELECT 4, 1, 'created', 'Saved as Draft for 2026-10-16', '2026-10-02 10:00:00'
WHERE NOT EXISTS (SELECT 1 FROM schedule_logs WHERE schedule_id = 4 AND action = 'created')
UNION ALL
SELECT 5, 1, 'created', 'Schedule created for 2026-10-20 06:00-10:00', '2026-10-02 10:15:00'
WHERE NOT EXISTS (SELECT 1 FROM schedule_logs WHERE schedule_id = 5 AND action = 'created')
UNION ALL
SELECT 5, 1, 'published', 'Status set to Active', '2026-10-02 10:16:00'
WHERE NOT EXISTS (SELECT 1 FROM schedule_logs WHERE schedule_id = 5 AND action = 'published');

INSERT INTO announcements (title, body, status, created_by, created_at)
SELECT 'Waste Segregation Reminder', 'Please separate biodegradable, recyclable, and residual waste before collection day.', 'Published', 1, '2026-10-01 10:00:00'
WHERE NOT EXISTS (SELECT 1 FROM announcements WHERE title = 'Waste Segregation Reminder')
UNION ALL
SELECT 'Collection Time Reminder', 'Place your waste outside before 6 AM on collection days. Trucks will not return after the scheduled window.', 'Published', 1, '2026-10-02 08:30:00'
WHERE NOT EXISTS (SELECT 1 FROM announcements WHERE title = 'Collection Time Reminder')
UNION ALL
SELECT 'Barangay Clean-Up Drive', 'Join our clean-up drive at the Barangay Hall this month. Details will follow by SMS.', 'Draft', 1, '2026-10-05 14:00:00'
WHERE NOT EXISTS (SELECT 1 FROM announcements WHERE title = 'Barangay Clean-Up Drive');