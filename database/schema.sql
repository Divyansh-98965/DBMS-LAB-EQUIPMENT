PRAGMA foreign_keys = OFF;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS maintenance;
DROP TABLE IF EXISTS borrowings;
DROP TABLE IF EXISTS consumables;
DROP TABLE IF EXISTS equipment;
DROP TABLE IF EXISTS equipment_categories;
DROP TABLE IF EXISTS laboratories;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;
PRAGMA foreign_keys = ON;

CREATE TABLE departments (
  department_id INTEGER PRIMARY KEY AUTOINCREMENT,
  department_name TEXT NOT NULL UNIQUE,
  department_code TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
  user_id INTEGER PRIMARY KEY AUTOINCREMENT,
  university_id TEXT NOT NULL UNIQUE,
  full_name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  phone TEXT,
  department_id INTEGER,
  role TEXT NOT NULL DEFAULT 'student' CHECK (role IN ('admin','technician','faculty','student')),
  account_status TEXT NOT NULL DEFAULT 'active' CHECK (account_status IN ('active','inactive')),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(department_id) ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE suppliers (
  supplier_id INTEGER PRIMARY KEY AUTOINCREMENT,
  supplier_name TEXT NOT NULL,
  contact_person TEXT,
  email TEXT,
  phone TEXT,
  address TEXT,
  gstin TEXT UNIQUE,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE laboratories (
  lab_id INTEGER PRIMARY KEY AUTOINCREMENT,
  lab_name TEXT NOT NULL,
  lab_code TEXT NOT NULL UNIQUE,
  department_id INTEGER NOT NULL,
  building TEXT,
  room_number TEXT,
  floor_number TEXT,
  lab_incharge_id INTEGER,
  capacity INTEGER NOT NULL DEFAULT 1 CHECK (capacity > 0),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(department_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (lab_incharge_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE equipment_categories (
  category_id INTEGER PRIMARY KEY AUTOINCREMENT,
  category_name TEXT NOT NULL UNIQUE,
  parent_category_id INTEGER,
  description TEXT,
  FOREIGN KEY (parent_category_id) REFERENCES equipment_categories(category_id) ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE equipment (
  equipment_id INTEGER PRIMARY KEY AUTOINCREMENT,
  asset_tag TEXT NOT NULL UNIQUE,
  serial_number TEXT UNIQUE,
  equipment_name TEXT NOT NULL,
  category_id INTEGER NOT NULL,
  lab_id INTEGER NOT NULL,
  manufacturer TEXT,
  model TEXT,
  purchase_date TEXT,
  purchase_price NUMERIC NOT NULL DEFAULT 0 CHECK (purchase_price >= 0),
  warranty_expiry TEXT,
  status TEXT NOT NULL DEFAULT 'available' CHECK (status IN ('available','issued','maintenance','damaged','lost','retired')),
  item_condition TEXT NOT NULL DEFAULT 'good' CHECK (item_condition IN ('excellent','good','fair','poor')),
  current_custodian_id INTEGER,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES equipment_categories(category_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (lab_id) REFERENCES laboratories(lab_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (current_custodian_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
);
CREATE INDEX idx_equipment_status ON equipment(status);
CREATE INDEX idx_equipment_lab ON equipment(lab_id);

CREATE TABLE consumables (
  consumable_id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_name TEXT NOT NULL,
  item_code TEXT NOT NULL UNIQUE,
  category TEXT,
  lab_id INTEGER NOT NULL,
  supplier_id INTEGER,
  quantity_available NUMERIC NOT NULL DEFAULT 0 CHECK (quantity_available >= 0),
  unit_of_measure TEXT NOT NULL DEFAULT 'units',
  reorder_level NUMERIC NOT NULL DEFAULT 0 CHECK (reorder_level >= 0),
  expiry_date TEXT,
  unit_price NUMERIC NOT NULL DEFAULT 0 CHECK (unit_price >= 0),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lab_id) REFERENCES laboratories(lab_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON UPDATE CASCADE ON DELETE SET NULL
);
CREATE INDEX idx_consumables_stock ON consumables(quantity_available, reorder_level);

CREATE TABLE borrowings (
  borrowing_id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  equipment_id INTEGER NOT NULL,
  approved_by INTEGER,
  issue_date TEXT,
  due_date TEXT NOT NULL,
  return_date TEXT,
  issue_condition TEXT NOT NULL DEFAULT 'good' CHECK (issue_condition IN ('excellent','good','fair','poor')),
  return_condition TEXT CHECK (return_condition IN ('excellent','good','fair','poor')),
  borrowing_status TEXT NOT NULL DEFAULT 'pending' CHECK (borrowing_status IN ('pending','active','returned','rejected','overdue')),
  remarks TEXT,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (approved_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
);
CREATE INDEX idx_borrowing_due ON borrowings(borrowing_status, due_date);
CREATE INDEX idx_borrowing_asset ON borrowings(equipment_id, borrowing_status);

CREATE TABLE maintenance (
  maintenance_id INTEGER PRIMARY KEY AUTOINCREMENT,
  equipment_id INTEGER NOT NULL,
  reported_by INTEGER NOT NULL,
  assigned_to INTEGER,
  maintenance_type TEXT NOT NULL DEFAULT 'corrective' CHECK (maintenance_type IN ('preventive','corrective','emergency','inspection','calibration')),
  issue_description TEXT NOT NULL,
  resolution TEXT,
  reported_date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completion_date TEXT,
  maintenance_cost NUMERIC NOT NULL DEFAULT 0 CHECK (maintenance_cost >= 0),
  next_calibration_date TEXT,
  calibration_result TEXT CHECK (calibration_result IN ('passed','failed','conditional')),
  maintenance_status TEXT NOT NULL DEFAULT 'open' CHECK (maintenance_status IN ('open','in_progress','completed','cancelled')),
  FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (reported_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
);
CREATE INDEX idx_maintenance_status ON maintenance(maintenance_status);

CREATE TABLE transactions (
  transaction_id INTEGER PRIMARY KEY AUTOINCREMENT,
  transaction_type TEXT NOT NULL CHECK (transaction_type IN ('equipment_transfer','consumable_purchase','consumable_issue','consumable_return','stock_adjustment')),
  equipment_id INTEGER,
  consumable_id INTEGER,
  source_lab_id INTEGER,
  destination_lab_id INTEGER,
  supplier_id INTEGER,
  performed_by INTEGER NOT NULL,
  quantity NUMERIC NOT NULL DEFAULT 1 CHECK (quantity > 0),
  transaction_date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reference_number TEXT,
  remarks TEXT,
  FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (consumable_id) REFERENCES consumables(consumable_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (source_lab_id) REFERENCES laboratories(lab_id) ON UPDATE CASCADE ON DELETE SET NULL,
  FOREIGN KEY (destination_lab_id) REFERENCES laboratories(lab_id) ON UPDATE CASCADE ON DELETE SET NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON UPDATE CASCADE ON DELETE SET NULL,
  FOREIGN KEY (performed_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT
);
CREATE INDEX idx_transaction_date ON transactions(transaction_date);
CREATE INDEX idx_transaction_type ON transactions(transaction_type);
