# LabTrace — Laboratory Equipment Management

A PHP + SQLite laboratory equipment and inventory management website prepared for an  academic DBMS project. The application uses a local SQLite database file and does not require XAMPP, MySQL, MariaDB or phpMyAdmin.

The  sample departments, laboratories, people, equipment and transactions are illustrative demonstration records..

## Requirements

- PHP 8.1 or newer
- PHP extensions: `PDO` and `pdo_sqlite`
- A modern browser
- Internet connection for Bootstrap, Font Awesome, Chart.js and Mermaid CDN assets. The core PHP application and local database remain on your computer.

## Installation & Setup

### Windows

#### 1. Install PHP 8.1+

Choose one of the following methods:

- **Method A: Windows Package Manager (`winget`) — Recommended**
  Open PowerShell or Command Prompt as Administrator and run:
  ```powershell
  winget install PHP.PHP.8.3
  ```
  *(Restart your terminal after installation so `php` is added to your PATH).*

- **Method B: Using Chocolatey or Scoop**
  ```powershell
  choco install php
  # or
  scoop install php
  ```

- **Method C: Manual Zip Download**
  1. Download the **VS16 x64 Thread Safe** zip package from [windows.php.net/download](https://windows.php.net/download/).
  2. Extract the archive to `C:\php`.
  3. Add `C:\php` to your System Environment Variable `PATH`:
     - Press <kbd>Win</kbd> + <kbd>R</kbd>, type `sysdm.cpl`, press Enter.
     - Go to the **Advanced** tab &rarr; **Environment Variables**.
     - Under **System variables**, select `Path` &rarr; click **Edit** &rarr; click **New** &rarr; add `C:\php`.
     - Click **OK** to save.

#### 2. Enable SQLite PDO Extension (Windows)

In your PHP installation directory (e.g. `C:\php` or your package manager path):
1. Copy `php.ini-development` to `php.ini` (if `php.ini` does not already exist).
2. Open `php.ini` in Notepad or any text editor.
3. Find the line `;extension_dir = "ext"` and remove the leading semicolon:
   ```ini
   extension_dir = "ext"
   ```
4. Find and uncomment the following extensions by removing the leading semicolons:
   ```ini
   extension=pdo_sqlite
   extension=sqlite3
   ```
5. Save the file and restart your terminal.

#### 3. Verify Installation

Check that PHP and SQLite are working:

```powershell
php -v
php -m | findstr /i sqlite
```

The output must show both `pdo_sqlite` and `sqlite3`.

#### 4. Run LabTrace on Windows

- **Option A (One-click):** Double-click `start.bat`.
- **Option B (PowerShell / Command Prompt):**
  ```powershell
  cd path\to\LabTrace
  php -S localhost:8000
  ```
- Open `http://localhost:8000` in your web browser.

---

### Linux (Ubuntu / Debian / Fedora / Arch)

#### 1. Install PHP & SQLite

Select your distribution:

- **Ubuntu / Debian / Linux Mint:**
  ```bash
  sudo apt update
  sudo apt install -y php php-cli php-sqlite3 php-mbstring
  ```

- **Fedora / RHEL / CentOS Stream / AlmaLinux:**
  ```bash
  sudo dnf install -y php php-cli php-pdo php-sqlite3
  ```

- **Arch Linux / Manjaro:**
  ```bash
  sudo pacman -S php php-sqlite
  ```
  *Note for Arch Linux:* Open `/etc/php/php.ini` and ensure `extension=pdo_sqlite` and `extension=sqlite3` are uncommented.

#### 2. Verify Installation

```bash
php -v
php -m | grep -i sqlite
```

Ensure `pdo_sqlite` is listed in the output.

#### 3. Run LabTrace on Linux

- **Option A (Convenience Script):**
  ```bash
  cd /path/to/LabTrace
  chmod +x start.sh
  ./start.sh
  ```
- **Option B (Direct command line):**
  ```bash
  cd /path/to/LabTrace
  php -S localhost:8000
  ```
- Open `http://localhost:8000` or `http://127.0.0.1:8000` in your browser.

> [!TIP]
> Ensure the application has permission to write to the `storage/` directory so the database can be created:
> ```bash
> chmod 775 storage
> ```

---

### macOS

#### 1. Install PHP

Using [Homebrew](https://brew.sh/):
```bash
brew install php
```

Homebrew PHP builds bundle `pdo_sqlite` by default. Verify with:
```bash
php -m | grep -i sqlite
```

#### 2. Run LabTrace on macOS

- **Option A:** Double-click or run `start.command`.
- **Option B:** Run in Terminal:
  ```bash
  cd /path/to/LabTrace
  php -S localhost:8000
  ```
- Open `http://localhost:8000` in Safari, Chrome, or Firefox.

## First launch and database

On first launch, LabTrace creates `storage/labtrace.db`, creates all 10 tables and inserts the demonstration records. You do not need to import SQL manually. `database/schema.sql` and `database/seed.sql` are included separately for DBMS inspection and academic submission.

The database is a single file at `storage/labtrace.db`. Back it up by copying that file while the application is stopped.

## Demo login

- Email: `admin@labtrace.local`
- Password: `password`

All seeded demo users use the same password. Change these accounts before using the app beyond a classroom demonstration.

## Included modules

- Dashboard with database counts, equipment distribution, low-stock register, laboratory register, open maintenance jobs and recent transactions
- Equipment register
- Laboratories
- Consumables inventory
- Borrowing requests, approvals and returns
- Maintenance and calibration records
- Equipment transfers and stock transaction ledger
- Departments, categories, suppliers and users
- **Relational Schema:** detailed table-by-table columns, SQLite types, primary/foreign/unique keys, nullability, defaults, validation domains, indexes, foreign-key actions and normalization notes
- **ER Diagram:** all 10 entities, key attributes, cardinalities, self-reference and operational relationships

## Demonstration dataset

The bundled database contains 10 core tables and a larger academic demonstration dataset:

| Table | Records |
|---|---:|
| departments | 6 |
| users | 13 |
| laboratories | 8 |
| equipment_categories | 8 |
| equipment | 17 |
| consumables | 16 |
| borrowings | 7 |
| maintenance | 7 |
| transactions | 12 |
| suppliers | 5 |

## Database notes

- Exactly 10 core tables are used.
- SQLite enforces primary keys, foreign keys, unique constraints, `CHECK` constraints and indexes.
- The application uses PDO prepared statements, password hashing, sessions and CSRF tokens.
- Borrowing and inventory workflows use database transactions. SQLite serializes writes rather than using MySQL-style `SELECT ... FOR UPDATE` row locks.
- SQLite does not support stored procedures. Views, triggers, joins, nested queries, indexes, constraints and transactions are supported. If your DBMS evaluation specifically requires stored procedures, explain this SQLite limitation or demonstrate stored procedures separately in MySQL.
- The `transactions` table is a compact event ledger. Nullable equipment, consumable, supplier and laboratory references are interpreted according to `transaction_type`; application validation must enforce the appropriate combination.

## Reset the demonstration database

Stop the PHP server, then delete `storage/labtrace.db`. Start the server again; LabTrace will recreate the database and insert the original sample data. **This permanently deletes all data stored in that local database.**

## Project structure

- `database/schema.sql` — SQLite schema for the 10 core tables
- `database/seed.sql` — demonstration records
- `storage/labtrace.db` — bundled SQLite database file
- `config/database.php` — PDO SQLite connection and first-run setup
- `includes/` — shared helpers and page layout
- `assets/` — CSS and JavaScript
- `index.php` — authenticated application and modules, including schema and ER documentation
- `login.php`, `logout.php` — authentication
- `start.bat` — Windows launcher
- `start.sh` — Linux launcher
- `start.command` — macOS launcher

## Important

This is an academic/local-development project, not a production deployment. Do not expose PHP's built-in development server directly to the public internet. For team-wide access, deploy the PHP app to a proper host and use a database setup appropriate for concurrent users.
