PRAGMA foreign_keys = ON;

INSERT INTO departments (department_name, department_code) VALUES
('Computer Science and Engineering','CSE'),
('Electronics and Communication Engineering','ECE'),
('Mechanical Engineering','ME'),
('Electrical Engineering','EE');

-- Demo password for every seeded user: password
INSERT INTO users (university_id,full_name,email,password_hash,phone,department_id,role) VALUES
('ADMIN001','System Administrator','admin@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990001',1,'admin'),
('TECH001','Aarav Sharma','aarav.tech@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990002',2,'technician'),
('FAC001','Priya Mehta','priya.faculty@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990003',1,'faculty'),
('STU001','Aman Verma','aman.student@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990004',1,'student'),
('STU002','Riya Singh','riya.student@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990005',2,'student');

INSERT INTO suppliers (supplier_name,contact_person,email,phone,address,gstin) VALUES
('Precision Instruments India','Karan Gupta','sales@precision.example','01140000001','New Delhi','07ABCDE1234F1Z5'),
('Electro Components Co.','Neha Rao','orders@electro.example','01140000002','Okhla, New Delhi',NULL);

INSERT INTO laboratories (lab_name,lab_code,department_id,building,room_number,floor_number,lab_incharge_id,capacity) VALUES
('Digital Systems Lab','CSE-DSL',1,'Block A','A-201','2',3,40),
('Electronics Lab','ECE-EL',2,'Block B','B-104','1',2,35),
('Mechanical Workshop','ME-MW',3,'Workshop Block','W-001','G',2,30);

INSERT INTO equipment_categories (category_name,description) VALUES
('Measurement Instruments','Meters and measurement equipment'),
('Electronics','Electronic test and development equipment'),
('Computing','Computers and peripherals'),
('Mechanical','Mechanical tools and instruments');

INSERT INTO equipment (asset_tag,serial_number,equipment_name,category_id,lab_id,manufacturer,model,purchase_date,purchase_price,warranty_expiry,status,item_condition) VALUES
('LT-OSC-001','SN-OSC-001','Digital Oscilloscope',2,2,'Tektronix','TBS1102B','2024-02-10',68000,'2027-02-10','available','excellent'),
('LT-DMM-001','SN-DMM-001','Digital Multimeter',1,2,'Fluke','117','2023-08-15',14500,'2026-08-15','available','good'),
('LT-FG-001','SN-FG-001','Function Generator',2,2,'Rigol','DG1022Z','2023-04-20',32000,'2026-04-20','maintenance','fair'),
('LT-PC-001','SN-PC-001','Laboratory Workstation',3,1,'Dell','OptiPlex 7010','2024-01-12',72000,'2027-01-12','available','good'),
('LT-CAL-001','SN-CAL-001','Vernier Caliper',4,3,'Mitutoyo','500-196-30','2022-06-01',8500,'2025-06-01','available','good');

INSERT INTO consumables (item_name,item_code,category,lab_id,supplier_id,quantity_available,unit_of_measure,reorder_level,expiry_date,unit_price) VALUES
('Resistor 1k Ohm','RES-1K','Electronic Components',2,2,420,'pieces',100,NULL,1.50),
('Breadboard','BRD-830','Prototyping',2,2,18,'pieces',10,NULL,120.00),
('Solder Wire','SLD-01','Workshop Supplies',2,2,3.5,'rolls',5,NULL,280.00),
('Nitrile Gloves','GLV-M','Safety',3,1,200,'pairs',50,'2027-12-31',4.00);

INSERT INTO borrowings (user_id,equipment_id,approved_by,issue_date,due_date,borrowing_status,issue_condition,remarks) VALUES
(4,4,3,datetime('now','-1 day'),datetime('now','+6 day'),'active','good','Course project use'),
(5,2,2,datetime('now','-10 day'),datetime('now','-3 day'),'overdue','good','Electronics practical');

UPDATE equipment SET status='issued',current_custodian_id=4 WHERE equipment_id=4;
UPDATE equipment SET status='issued',current_custodian_id=5 WHERE equipment_id=2;

INSERT INTO maintenance (equipment_id,reported_by,assigned_to,maintenance_type,issue_description,reported_date,maintenance_status) VALUES
(3,2,2,'corrective','Output amplitude is unstable',datetime('now','-2 day'),'in_progress'),
(5,2,2,'calibration','Annual calibration check',datetime('now','-20 day'),'completed');

INSERT INTO transactions (transaction_type,consumable_id,supplier_id,performed_by,quantity,reference_number,remarks) VALUES
('consumable_purchase',1,2,1,300,'PO-DEMO-001','Initial stock receipt'),
('consumable_issue',2,NULL,2,2,'ISS-DEMO-001','Breadboards issued for practical');

-- Additional NSUT-style academic demo records for a fuller classroom dataset.
INSERT INTO departments (department_name, department_code) VALUES
('Information Technology','IT'),
('Instrumentation and Control Engineering','ICE');

INSERT INTO users (university_id,full_name,email,password_hash,phone,department_id,role) VALUES
('TECH002','Rohan Kapoor','rohan.tech@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990010',1,'technician'),
('FAC002','Dr. Nidhi Rao','nidhi.faculty@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990011',2,'faculty'),
('FAC003','Dr. Kabir Sethi','kabir.faculty@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990012',3,'faculty'),
('STU003','Ishita Jain','ishita.student@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990013',1,'student'),
('STU004','Dev Malhotra','dev.student@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990014',2,'student'),
('STU005','Ananya Das','ananya.student@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990015',4,'student'),
('STU006','Arjun Bhatia','arjun.student@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990016',5,'student'),
('STU007','Meera Nair','meera.student@labtrace.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9999990017',6,'student');

INSERT INTO suppliers (supplier_name,contact_person,email,phone,address,gstin) VALUES
('Labtech Scientific','Sanjay Bansal','orders@labtech.example','01141000003','Kirti Nagar, New Delhi',NULL),
('North India Electronics','Pooja Arora','sales@nie.example','01141000004','Lajpat Nagar, New Delhi','07PQRSX5678L1Z2'),
('Metro Safety Supplies','Rahul Khanna','support@metrosafety.example','01141000005','Mayapuri, New Delhi',NULL);

INSERT INTO laboratories (lab_name,lab_code,department_id,building,room_number,floor_number,lab_incharge_id,capacity) VALUES
('Data Structures Lab','CSE-DS',1,'Academic Block','AB-302','3',6,45),
('Networks and Security Lab','CSE-NS',1,'Academic Block','AB-305','3',3,36),
('Embedded Systems Lab','ECE-ES',2,'Electronics Block','EB-202','2',7,32),
('Power Systems Lab','EE-PS',4,'Electrical Block','EL-108','1',2,28),
('Instrumentation Lab','ICE-IL',6,'Academic Block','AB-112','1',6,30);

INSERT INTO equipment_categories (category_name,parent_category_id,description) VALUES
('Networking Equipment',3,'Routers, switches and network testing equipment'),
('Robotics',4,'Robotic platforms, actuators and control equipment'),
('Power and Electrical',1,'Power supplies, electrical machines and protection equipment'),
('Safety Equipment',NULL,'Personal protective and laboratory safety equipment');

INSERT INTO equipment (asset_tag,serial_number,equipment_name,category_id,lab_id,manufacturer,model,purchase_date,purchase_price,warranty_expiry,status,item_condition) VALUES
('LT-RTR-001','SN-RTR-001','Enterprise Router',5,5,'Cisco','ISR 4321','2024-03-18',89000,'2027-03-18','available','good'),
('LT-SWT-001','SN-SWT-001','Managed Network Switch',5,5,'Cisco','Catalyst 2960','2023-11-05',42000,'2026-11-05','available','good'),
('LT-ARD-001','SN-ARD-001','Microcontroller Development Kit',6,7,'Arduino','Mega 2560','2024-08-12',4200,'2026-08-12','available','excellent'),
('LT-PLC-001','SN-PLC-001','Programmable Logic Controller',6,7,'Siemens','S7-1200','2023-07-22',56000,'2026-07-22','available','good'),
('LT-PSU-001','SN-PSU-001','Bench Power Supply',7,8,'Keysight','E36312A','2024-05-09',73500,'2027-05-09','available','excellent'),
('LT-MTR-001','SN-MTR-001','Three Phase Induction Motor',7,8,'Crompton','IE3-2HP','2022-09-14',18500,'2025-09-14','available','fair'),
('LT-ROB-001','SN-ROB-001','Robotic Arm Trainer',6,3,'Dobots','Magician','2024-01-29',112000,'2027-01-29','available','good'),
('LT-MIC-001','SN-MIC-001','Digital Microscope',1,8,'Olympus','CX23','2023-12-02',39500,'2026-12-02','available','good'),
('LT-SEN-001','SN-SEN-001','Industrial Sensor Trainer',1,8,'Festo','TP 1316','2024-06-11',64000,'2027-06-11','available','excellent'),
('LT-LAP-002','SN-LAP-002','Student Workstation',3,4,'HP','ProDesk 400','2023-10-17',61000,'2026-10-17','available','good'),
('LT-CAL-002','SN-CAL-002','Digital Vernier Caliper',4,3,'Mitutoyo','CD-6 ASX','2024-02-14',12400,'2027-02-14','available','excellent'),
('LT-OSC-002','SN-OSC-002','Mixed Signal Oscilloscope',2,7,'Rigol','MSO5074','2024-04-25',138000,'2027-04-25','available','good');

INSERT INTO consumables (item_name,item_code,category,lab_id,supplier_id,quantity_available,unit_of_measure,reorder_level,expiry_date,unit_price) VALUES
('Resistor 10k Ohm','RES-10K','Electronic Components',2,2,650,'pieces',150,NULL,1.25),
('Capacitor 100uF','CAP-100U','Electronic Components',2,2,280,'pieces',75,NULL,3.50),
('Jumper Wire Set','JMP-SET','Prototyping',7,4,42,'sets',15,NULL,65.00),
('Soldering Flux','FLX-100','Workshop Supplies',7,3,8,'bottles',3,'2028-03-31',95.00),
('Isopropyl Alcohol','IPA-1L','Cleaning',8,3,12,'litres',4,'2027-06-30',210.00),
('Safety Goggles','GOG-01','Safety',3,5,34,'pieces',12,NULL,85.00),
('Disposable Gloves','GLV-L','Safety',8,5,90,'pairs',40,'2027-11-30',5.00),
('Patch Cable Cat6','CAT6-2M','Networking',5,4,26,'pieces',10,NULL,110.00),
('Breadboard 830 Point','BRD-830B','Prototyping',7,2,24,'pieces',10,NULL,135.00),
('Copper Wire 1mm','CU-1MM','Electrical Supplies',8,1,18,'metres',8,NULL,42.00),
('Thermal Paste','THM-PASTE','Computing Supplies',4,4,7,'tubes',3,'2028-01-31',160.00),
('Fuse 5A','FUSE-5A','Electrical Supplies',8,1,65,'pieces',20,NULL,8.00);

INSERT INTO borrowings (user_id,equipment_id,approved_by,issue_date,due_date,return_date,borrowing_status,issue_condition,return_condition,remarks) VALUES
(7,1,3,datetime('now','-3 day'),datetime('now','+4 day'),NULL,'active','excellent',NULL,'Oscilloscope for signals practical'),
(8,12,2,datetime('now','-2 day'),datetime('now','+5 day'),NULL,'active','good',NULL,'Embedded systems mini project'),
(9,10,6,NULL,datetime('now','+8 day'),NULL,'pending','good',NULL,'Requested for programming laboratory'),
(6,8,3,datetime('now','-15 day'),datetime('now','-8 day'),datetime('now','-8 day'),'returned','good','good','Returned after instrumentation practical'),
(4,11,2,datetime('now','-1 day'),datetime('now','+6 day'),NULL,'active','excellent',NULL,'Measurement assignment');

UPDATE equipment SET status='issued',current_custodian_id=7 WHERE equipment_id=1;
UPDATE equipment SET status='issued',current_custodian_id=8 WHERE equipment_id=12;
UPDATE equipment SET status='issued',current_custodian_id=7 WHERE equipment_id=16;

INSERT INTO maintenance (equipment_id,reported_by,assigned_to,maintenance_type,issue_description,resolution,reported_date,completion_date,maintenance_cost,next_calibration_date,calibration_result,maintenance_status) VALUES
(1,7,2,'inspection','Annual visual inspection and safety check',NULL,datetime('now','-5 day'),NULL,0,NULL,NULL,'open'),
(6,2,6,'preventive','Bearing noise during operation','Lubricated bearing assembly and verified vibration levels',datetime('now','-30 day'),datetime('now','-28 day'),1800,NULL,NULL,'completed'),
(9,7,2,'calibration','Sensor output requires calibration verification',NULL,datetime('now','-4 day'),NULL,0,'2027-11-05',NULL,'in_progress'),
(10,6,2,'corrective','Workstation intermittently shuts down',NULL,datetime('now','-1 day'),NULL,0,NULL,NULL,'open'),
(5,2,2,'inspection','Verify output voltage under rated load','Output verified within manufacturer tolerance',datetime('now','-12 day'),datetime('now','-11 day'),500,NULL,'passed','completed');

INSERT INTO transactions (transaction_type,equipment_id,consumable_id,source_lab_id,destination_lab_id,supplier_id,performed_by,quantity,reference_number,remarks) VALUES
('equipment_transfer',12,NULL,3,7,NULL,2,1,'TRF-2026-001','Robotics trainer moved to embedded systems lab'),
('consumable_purchase',NULL,9,NULL,8,3,1,10,'PO-2026-014','Cleaning solvent stock replenishment'),
('consumable_issue',NULL,7,NULL,7,NULL,2,5,'ISS-2026-021','Jumper wire sets issued for embedded practical'),
('consumable_return',NULL,7,NULL,7,NULL,2,2,'RET-2026-004','Unused jumper wire sets returned'),
('stock_adjustment',NULL,1,NULL,2,NULL,2,1,'ADJ-2026-008','Stock count correction after physical verification'),
('consumable_purchase',NULL,12,NULL,5,4,1,20,'PO-2026-015','Cat6 patch cable purchase'),
('consumable_issue',NULL,14,NULL,8,NULL,2,3,'ISS-2026-022','Copper wire issued for electrical practical'),
('equipment_transfer',16,NULL,3,1,NULL,2,1,'TRF-2026-002','Digital caliper temporarily assigned to measurement lab'),
('consumable_purchase',NULL,10,NULL,3,5,1,50,'PO-2026-016','Safety goggles replenishment'),
('consumable_issue',NULL,16,NULL,8,NULL,2,10,'ISS-2026-023','Fuses issued for power systems practical');
