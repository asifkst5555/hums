CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  username VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'viewer', 'operator') NOT NULL,
  union_name VARCHAR(190) NOT NULL DEFAULT 'all',
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unions (
  name VARCHAR(190) NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS beneficiaries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  mis_number VARCHAR(40) NULL,
  name VARCHAR(190) NOT NULL,
  name_en VARCHAR(190) NULL,
  gender VARCHAR(32) NULL,
  nid VARCHAR(32) NOT NULL,
  program VARCHAR(190) NOT NULL,
  union_name VARCHAR(190) NOT NULL,
  phone VARCHAR(64) NULL,
  dob DATE NULL,
  father_en VARCHAR(190) NULL,
  father VARCHAR(190) NULL,
  mother_en VARCHAR(190) NULL,
  mother VARCHAR(190) NULL,
  spouse_name_en VARCHAR(190) NULL,
  spouse_name_bn VARCHAR(190) NULL,
  bank_mfs VARCHAR(190) NULL,
  account_number VARCHAR(80) NULL,
  age INT NULL,
  division_name VARCHAR(120) NULL,
  district_name VARCHAR(120) NULL,
  upazila_name VARCHAR(120) NULL,
  ward_name VARCHAR(120) NULL,
  addr VARCHAR(255) NULL,
  status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_beneficiaries_nid (nid),
  INDEX idx_beneficiaries_phone (phone),
  INDEX idx_beneficiaries_program (program)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS institutions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  type VARCHAR(120) NOT NULL,
  union_name VARCHAR(190) NOT NULL,
  students INT UNSIGNED NOT NULL DEFAULT 0,
  head VARCHAR(190) NULL,
  phone VARCHAR(64) NULL,
  addr VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (id, name, username, password_hash, role, union_name, status) VALUES
  (1, 'Upazila Executive Officer', 'admin', '$2y$10$QyHCghCCjy3X1MiQdff2x.GHF/N7Bg9wvr305x8KjOqGevVUpaxfm', 'admin', 'all', 'active'),
  (2, 'Data Viewer', 'viewer', '$2y$10$39acwFZ2LvPIkMRglBvWxuGtcoig9M/CBtIB2vqIBFeGLIqfDORcW', 'viewer', 'all', 'active');

INSERT IGNORE INTO unions (name) VALUES
  ('Hathazari Paurashava'),
  ('Fatepur'),
  ('Gumanmardan'),
  ('Mekhal'),
  ('Mirzapur'),
  ('Buri Char'),
  ('Chipatoli'),
  ('Katirhat'),
  ('Uttar Madarsa'),
  ('Dakshin Madarsa'),
  ('Dhalai'),
  ('Nangalmora');

INSERT IGNORE INTO beneficiaries (id, name, nid, program, union_name, phone, dob, father, mother, addr, status) VALUES
  (1, 'Rahima Begum', '1234567890123', 'Old Age Allowance', 'Fatepur', '01812-345678', '1952-03-15', 'Abdul Karim', 'Jobeda Begum', 'North Fatepur, Ward-3', 'active'),
  (2, 'Karim Uddin', '9876543210987', 'Disability Allowance', 'Mekhal', '01734-567890', '1978-07-22', 'Motiur Rahman', 'Sufia Begum', 'Mekhal Bazar, Ward-1', 'active'),
  (3, 'Fatema Khatun', '1122334455667', 'Widow Allowance', 'Gumanmardan', '01901-234567', '1965-11-10', 'Md Hanif', 'Anowara Begum', 'South Para', 'active'),
  (4, 'Ali Hossain', '5566778899001', 'VGF', 'Hathazari Paurashava', '01615-678901', '1980-05-18', 'Abdul Hossain', 'Nurjahan Begum', 'Paurashava Ward-5', 'pending');

INSERT IGNORE INTO institutions (id, name, type, union_name, students, head, phone, addr) VALUES
  (1, 'Hathazari Govt Pilot High School', 'Secondary School', 'Hathazari Paurashava', 1200, 'Nurul Islam', '01812-111222', 'Hathazari Paurashava'),
  (2, 'Hathazari Govt College', 'College', 'Hathazari Paurashava', 3500, 'Abdul Mannan', '01711-222333', 'Hathazari Paurashava'),
  (3, 'Fatepur Govt Primary School', 'Primary School', 'Fatepur', 450, 'Rahima Akter', '01922-333444', 'Fatepur Union'),
  (4, 'Al-Jamiya Arabia Madrasa', 'Madrasa', 'Hathazari Paurashava', 5000, 'Mufti Ahmad Shafi', '01811-444555', 'Hathazari Paurashava');
