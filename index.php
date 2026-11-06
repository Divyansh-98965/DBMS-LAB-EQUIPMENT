<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';
require_login();
verify_csrf();

$user = current_user();
$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard','equipment','laboratories','consumables','borrowings','maintenance','transactions','departments','categories','suppliers','users','schema','er_diagram'];
if (!in_array($page, $allowed, true)) $page = 'dashboard';

function rows(PDO $pdo, string $sql, array $params=[]): array {
    $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll();
}
function scalar(PDO $pdo, string $sql): int|float {
    return (float)$pdo->query($sql)->fetchColumn();
}
function options(PDO $pdo, string $sql, string $id, string $label): array {
    return rows($pdo, "SELECT $id AS id, $label AS label FROM (" . $sql . ") x");
}
function select_options(PDO $pdo, string $table, string $id, string $label): array {
    return $pdo->query("SELECT `$id` AS id, `$label` AS label FROM `$table` ORDER BY `$label`")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_equipment') {
            require_role(['admin','technician']);
            $id=(int)($_POST['equipment_id'] ?? 0);
            $data=[trim($_POST['asset_tag']),trim($_POST['serial_number']) ?: null,trim($_POST['equipment_name']),(int)$_POST['category_id'],(int)$_POST['lab_id'],trim($_POST['manufacturer']),trim($_POST['model']),$_POST['purchase_date'] ?: null,(float)$_POST['purchase_price'],$_POST['warranty_expiry'] ?: null,$_POST['status'],$_POST['item_condition']];
            if ($id) {
                $st=$pdo->prepare("UPDATE equipment SET asset_tag=?,serial_number=?,equipment_name=?,category_id=?,lab_id=?,manufacturer=?,model=?,purchase_date=?,purchase_price=?,warranty_expiry=?,status=?,item_condition=? WHERE equipment_id=?");
                $st->execute([...$data,$id]);
            } else {
                $st=$pdo->prepare("INSERT INTO equipment (asset_tag,serial_number,equipment_name,category_id,lab_id,manufacturer,model,purchase_date,purchase_price,warranty_expiry,status,item_condition) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $st->execute($data);
            }
            flash('Equipment saved successfully.');
        } elseif ($action === 'save_lab') {
            require_role(['admin']);
            $st=$pdo->prepare("INSERT INTO laboratories (lab_name,lab_code,department_id,building,room_number,floor_number,lab_incharge_id,capacity) VALUES (?,?,?,?,?,?,?,?)");
            $st->execute([trim($_POST['lab_name']),trim($_POST['lab_code']),(int)$_POST['department_id'],trim($_POST['building']),trim($_POST['room_number']),trim($_POST['floor_number']),($_POST['lab_incharge_id'] ?: null),(int)$_POST['capacity']]); flash('Laboratory created.');
        } elseif ($action === 'save_consumable') {
            require_role(['admin','technician']);
            $st=$pdo->prepare("INSERT INTO consumables (item_name,item_code,category,lab_id,supplier_id,quantity_available,unit_of_measure,reorder_level,expiry_date,unit_price) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $st->execute([trim($_POST['item_name']),trim($_POST['item_code']),trim($_POST['category']),(int)$_POST['lab_id'],($_POST['supplier_id'] ?: null),(float)$_POST['quantity_available'],trim($_POST['unit_of_measure']),(float)$_POST['reorder_level'],($_POST['expiry_date'] ?: null),(float)$_POST['unit_price']]); flash('Consumable added.');
        } elseif ($action === 'borrow_request') {
            $equipmentId=(int)$_POST['equipment_id'];
            $due=$_POST['due_date'];
            $pdo->beginTransaction();
            $lock=$pdo->prepare("SELECT status FROM equipment WHERE equipment_id=?"); $lock->execute([$equipmentId]); $asset=$lock->fetch();
            if (!$asset || $asset['status'] !== 'available') throw new RuntimeException('That equipment is not currently available.');
            $st=$pdo->prepare("INSERT INTO borrowings (user_id,equipment_id,due_date,borrowing_status,remarks) VALUES (?,?,?,'pending',?)");
            $st->execute([(int)$user['user_id'],$equipmentId,$due,trim($_POST['remarks'])]);
            $pdo->commit(); flash('Borrowing request submitted for approval.');
        } elseif ($action === 'approve_borrowing') {
            require_role(['admin','technician','faculty']);
            $id=(int)$_POST['borrowing_id'];
            $pdo->beginTransaction();
            $st=$pdo->prepare("SELECT b.equipment_id,b.borrowing_status,e.status FROM borrowings b JOIN equipment e ON e.equipment_id=b.equipment_id WHERE b.borrowing_id=?");
            $st->execute([$id]); $b=$st->fetch();
            if (!$b || $b['borrowing_status']!=='pending' || $b['status']!=='available') throw new RuntimeException('Request is no longer eligible for approval.');
            $pdo->prepare("UPDATE borrowings SET borrowing_status='active',approved_by=?,issue_date=datetime('now') WHERE borrowing_id=?")->execute([(int)$user['user_id'],$id]);
            $pdo->prepare("UPDATE equipment SET status='issued',current_custodian_id=(SELECT user_id FROM borrowings WHERE borrowing_id=?) WHERE equipment_id=?")->execute([$id,$b['equipment_id']]);
            $pdo->commit(); flash('Borrowing approved and equipment issued.');
        } elseif ($action === 'return_borrowing') {
            require_role(['admin','technician','faculty']);
            $id=(int)$_POST['borrowing_id'];
            $pdo->beginTransaction();
            $st=$pdo->prepare("SELECT equipment_id,borrowing_status FROM borrowings WHERE borrowing_id=?"); $st->execute([$id]); $b=$st->fetch();
            if (!$b || !in_array($b['borrowing_status'],['active','overdue'],true)) throw new RuntimeException('This borrowing cannot be returned.');
            $condition=$_POST['return_condition'];
            $pdo->prepare("UPDATE borrowings SET borrowing_status='returned',return_date=datetime('now'),return_condition=? WHERE borrowing_id=?")->execute([$condition,$id]);
            $status=in_array($condition,['poor','fair'],true) ? 'damaged' : 'available';
            $pdo->prepare("UPDATE equipment SET status=?,current_custodian_id=NULL,item_condition=? WHERE equipment_id=?")->execute([$status,$condition,$b['equipment_id']]);
            if ($status==='damaged') {
                $pdo->prepare("INSERT INTO maintenance (equipment_id,reported_by,maintenance_type,issue_description,maintenance_status) VALUES (?,?,'corrective','Damage reported during equipment return','open')")->execute([$b['equipment_id'],(int)$user['user_id']]);
                $pdo->prepare("UPDATE equipment SET status='maintenance' WHERE equipment_id=?")->execute([$b['equipment_id']]);
            }
            $pdo->commit(); flash('Equipment return recorded.');
        } elseif ($action === 'save_maintenance') {
            require_role(['admin','technician']);
            $st=$pdo->prepare("INSERT INTO maintenance (equipment_id,reported_by,assigned_to,maintenance_type,issue_description,reported_date,next_calibration_date,maintenance_status) VALUES (?,?,?,?,?,datetime('now'),?,'open')");
            $st->execute([(int)$_POST['equipment_id'],(int)$user['user_id'],($_POST['assigned_to'] ?: null),$_POST['maintenance_type'],trim($_POST['issue_description']),($_POST['next_calibration_date'] ?: null)]);
            $pdo->prepare("UPDATE equipment SET status='maintenance' WHERE equipment_id=?")->execute([(int)$_POST['equipment_id']]); flash('Maintenance record created.');
        } elseif ($action === 'complete_maintenance') {
            require_role(['admin','technician']);
            $id=(int)$_POST['maintenance_id'];
            $st=$pdo->prepare("SELECT equipment_id FROM maintenance WHERE maintenance_id=?"); $st->execute([$id]); $m=$st->fetch();
            if (!$m) throw new RuntimeException('Maintenance record not found.');
            $pdo->prepare("UPDATE maintenance SET maintenance_status='completed',resolution=?,maintenance_cost=?,completion_date=datetime('now'),calibration_result=? WHERE maintenance_id=?")->execute([trim($_POST['resolution']),(float)$_POST['maintenance_cost'],($_POST['calibration_result'] ?: null),$id]);
            $pdo->prepare("UPDATE equipment SET status='available' WHERE equipment_id=? AND status='maintenance'")->execute([$m['equipment_id']]); flash('Maintenance marked completed.');
        } elseif ($action === 'record_transaction') {
            require_role(['admin','technician']);
            $type=$_POST['transaction_type'];
            $qty=(float)$_POST['quantity'];
            $consumableId=(int)($_POST['consumable_id'] ?? 0);
            $pdo->beginTransaction();
            if (in_array($type,['consumable_issue','consumable_return','stock_adjustment','consumable_purchase'],true)) {
                $st=$pdo->prepare("SELECT quantity_available FROM consumables WHERE consumable_id=?"); $st->execute([$consumableId]); $c=$st->fetch();
                if (!$c) throw new RuntimeException('Consumable not found.');
                $delta = match($type) {'consumable_issue' => -$qty, 'consumable_return','consumable_purchase' => $qty, default => $qty};
                if ($type==='stock_adjustment') $delta=(float)$_POST['adjustment_delta'];
                if ((float)$c['quantity_available']+$delta < 0) throw new RuntimeException('Insufficient stock. Negative inventory is not allowed.');
                $pdo->prepare("UPDATE consumables SET quantity_available=quantity_available+? WHERE consumable_id=?")->execute([$delta,$consumableId]);
            }
            $st=$pdo->prepare("INSERT INTO transactions (transaction_type,equipment_id,consumable_id,source_lab_id,destination_lab_id,supplier_id,performed_by,quantity,reference_number,remarks) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $st->execute([$type,($_POST['equipment_id'] ?: null),($consumableId ?: null),($_POST['source_lab_id'] ?: null),($_POST['destination_lab_id'] ?: null),($_POST['supplier_id'] ?: null),(int)$user['user_id'],$qty,trim($_POST['reference_number']),trim($_POST['remarks'])]);
            if ($type==='equipment_transfer' && !empty($_POST['equipment_id']) && !empty($_POST['destination_lab_id'])) {
                $pdo->prepare("UPDATE equipment SET lab_id=? WHERE equipment_id=?")->execute([(int)$_POST['destination_lab_id'],(int)$_POST['equipment_id']]);
            }
            $pdo->commit(); flash('Transaction recorded.');
        } elseif ($action === 'save_department') {
            require_role(['admin']);
            $st=$pdo->prepare("INSERT INTO departments (department_name,department_code) VALUES (?,?)");
            $st->execute([trim($_POST['department_name']),trim($_POST['department_code'])]); flash('Department created.');
        } elseif ($action === 'save_category') {
            require_role(['admin']);
            $st=$pdo->prepare("INSERT INTO equipment_categories (category_name,parent_category_id,description) VALUES (?,?,?)");
            $st->execute([trim($_POST['category_name']),($_POST['parent_category_id'] ?: null),trim($_POST['description'])]); flash('Category created.');
        } elseif ($action === 'save_supplier') {
            require_role(['admin']);
            $st=$pdo->prepare("INSERT INTO suppliers (supplier_name,contact_person,email,phone,address,gstin) VALUES (?,?,?,?,?,?)");
            $st->execute([trim($_POST['supplier_name']),trim($_POST['contact_person']),trim($_POST['email']),trim($_POST['phone']),trim($_POST['address']),($_POST['gstin'] ?: null)]); flash('Supplier created.');
        } elseif ($action === 'save_user') {
            require_role(['admin']);
            $hash=password_hash($_POST['password'],PASSWORD_DEFAULT);
            $st=$pdo->prepare("INSERT INTO users (university_id,full_name,email,password_hash,phone,department_id,role) VALUES (?,?,?,?,?,?,?)");
            $st->execute([trim($_POST['university_id']),trim($_POST['full_name']),trim($_POST['email']),$hash,trim($_POST['phone']),($_POST['department_id'] ?: null),$_POST['role']]); flash('User created.');
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($ex->getMessage(), 'danger');
    }
    redirect('index.php?page=' . urlencode($page));
}

require __DIR__ . '/includes/header.php';

if ($page === 'dashboard') {
    $stats = [
      ['Total equipment',scalar($pdo,"SELECT COUNT(*) FROM equipment"),'fa-microscope'],
      ['Available',scalar($pdo,"SELECT COUNT(*) FROM equipment WHERE status='available'"),'fa-circle-check'],
      ['On loan',scalar($pdo,"SELECT COUNT(*) FROM equipment WHERE status='issued'"),'fa-arrow-up-right-from-square'],
      ['Maintenance',scalar($pdo,"SELECT COUNT(*) FROM equipment WHERE status='maintenance'"),'fa-screwdriver-wrench'],
      ['Laboratories',scalar($pdo,"SELECT COUNT(*) FROM laboratories"),'fa-building'],
      ['Overdue loans',scalar($pdo,"SELECT COUNT(*) FROM borrowings WHERE borrowing_status IN ('active','overdue') AND due_date<datetime('now')"),'fa-clock'],
      ['Registered users',scalar($pdo,"SELECT COUNT(*) FROM users WHERE account_status='active'"),'fa-users'],
      ['Consumable items',scalar($pdo,"SELECT COUNT(*) FROM consumables"),'fa-boxes-stacked'],
      ['Pending requests',scalar($pdo,"SELECT COUNT(*) FROM borrowings WHERE borrowing_status='pending'"),'fa-clipboard-list'],
    ];
    $recent=rows($pdo,"SELECT t.transaction_id,t.transaction_type,t.transaction_date,t.remarks,u.full_name FROM transactions t JOIN users u ON u.user_id=t.performed_by ORDER BY t.transaction_date DESC LIMIT 6");
    $low=rows($pdo,"SELECT item_name,quantity_available,unit_of_measure,reorder_level FROM consumables WHERE quantity_available<=reorder_level ORDER BY item_name LIMIT 5");
    $chart=rows($pdo,"SELECT c.category_name,COUNT(e.equipment_id) total FROM equipment_categories c LEFT JOIN equipment e ON e.category_id=c.category_id GROUP BY c.category_id,c.category_name ORDER BY total DESC");
    $labSummary=rows($pdo,"SELECT l.lab_code,l.lab_name,d.department_code,COUNT(DISTINCT e.equipment_id) equipment_count,COUNT(DISTINCT c.consumable_id) consumable_types,l.capacity FROM laboratories l JOIN departments d ON d.department_id=l.department_id LEFT JOIN equipment e ON e.lab_id=l.lab_id LEFT JOIN consumables c ON c.lab_id=l.lab_id GROUP BY l.lab_id ORDER BY d.department_code,l.lab_name");
    $openMaintenance=rows($pdo,"SELECT m.maintenance_id,e.asset_tag,e.equipment_name,m.maintenance_type,m.maintenance_status,m.reported_date FROM maintenance m JOIN equipment e ON e.equipment_id=m.equipment_id WHERE m.maintenance_status IN ('open','in_progress') ORDER BY m.reported_date DESC LIMIT 6");
    ?>
    <div class="row g-3 mb-4"><?php foreach($stats as [$label,$value,$icon]): ?><div class="col-6 col-xl-4"><div class="stat-card"><div class="stat-top"><span><?= e($label) ?></span><span class="stat-icon"><i class="fa-solid <?= e($icon) ?>"></i></span></div><div class="stat-value"><?= number_format($value) ?></div><div class="stat-note">Live database count</div></div></div><?php endforeach; ?></div>
    <div class="row g-3"><div class="col-xl-7"><div class="card-panel"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="section-heading">Equipment by category</h2><div class="section-sub">Registered assets grouped by equipment classification</div></div></div><canvas id="categoryChart" height="125"></canvas></div></div>
    <div class="col-xl-5"><div class="card-panel"><h2 class="section-heading">Low stock register</h2><div class="section-sub mb-3">Consumables at or below their reorder level</div><?php if (!$low): ?><div class="empty-state">No items currently require replenishment.</div><?php else: foreach($low as $item): ?><div class="d-flex justify-content-between py-2 border-bottom"><div><strong><?= e($item['item_name']) ?></strong><div class="text-secondary small">Reorder at <?= e($item['reorder_level']) ?> <?= e($item['unit_of_measure']) ?></div></div><span class="badge-soft badge-orange"><?= e($item['quantity_available']) ?> left</span></div><?php endforeach; endif; ?></div></div>
    <div class="col-12"><div class="card-panel"><div class="mb-3"><h2 class="section-heading">Laboratory register</h2><div class="section-sub">Equipment and consumable coverage by laboratory</div></div><div class="table-wrap"><table class="table"><thead><tr><th>Lab code</th><th>Laboratory</th><th>Department</th><th>Equipment assets</th><th>Consumable types</th><th>Capacity</th></tr></thead><tbody><?php foreach($labSummary as $l): ?><tr><td><strong><?= e($l['lab_code']) ?></strong></td><td><?= e($l['lab_name']) ?></td><td><?= e($l['department_code']) ?></td><td><?= e($l['equipment_count']) ?></td><td><?= e($l['consumable_types']) ?></td><td><?= e($l['capacity']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    <div class="col-xl-6"><div class="card-panel h-100"><h2 class="section-heading mb-3">Open maintenance jobs</h2><div class="table-wrap"><table class="table"><thead><tr><th>Asset</th><th>Work type</th><th>Status</th><th>Reported</th></tr></thead><tbody><?php foreach($openMaintenance as $m): ?><tr><td><strong><?= e($m['asset_tag']) ?></strong><div class="text-secondary"><?= e($m['equipment_name']) ?></div></td><td><?= e($m['maintenance_type']) ?></td><td><span class="badge-soft badge-orange"><?= e(str_replace('_',' ',$m['maintenance_status'])) ?></span></td><td><?= e(substr($m['reported_date'],0,10)) ?></td></tr><?php endforeach; ?><?php if(!$openMaintenance): ?><tr><td colspan="4" class="text-secondary">No open maintenance jobs.</td></tr><?php endif; ?></tbody></table></div></div></div>
    <div class="col-xl-6"><div class="card-panel h-100"><h2 class="section-heading mb-3">Recent transactions</h2><div class="table-wrap"><table class="table"><thead><tr><th>Reference</th><th>Type</th><th>Performed by</th><th>Date</th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td>#<?= e($r['transaction_id']) ?></td><td><?= e(str_replace('_',' ',$r['transaction_type'])) ?></td><td><?= e($r['full_name']) ?></td><td><?= e(substr($r['transaction_date'],0,16)) ?></td></tr><?php endforeach; ?><?php if(!$recent): ?><tr><td colspan="4" class="text-center text-secondary">No transactions recorded yet.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
    <script>window.labtraceChart={labels:<?= json_encode(array_column($chart,'category_name')) ?>,values:<?= json_encode(array_map('intval',array_column($chart,'total'))) ?>};</script>
<?php
 } elseif ($page === 'schema') {
    $tableInfo = [
      'departments' => ['Academic departments','Master table for the university departments that own laboratories and are associated with users.','Department identity is stored once; dependent tables reference department_id.'],
      'users' => ['System users','Students, faculty, technicians and administrators who use or operate LabTrace.','Department membership is optional. Passwords are stored as hashes, never as plain text.'],
      'laboratories' => ['Laboratory locations','Physical laboratories, their department, room, capacity and designated in-charge.','Each laboratory belongs to exactly one department; its in-charge is an optional user reference.'],
      'equipment_categories' => ['Equipment classification','Hierarchical classification of reusable equipment assets.','parent_category_id is a self-referencing foreign key for category hierarchies.'],
      'equipment' => ['Equipment register','Individually identifiable assets tracked by asset tag, serial number, location, condition and status.','Each asset has one category and one current laboratory; current_custodian_id is optional.'],
      'consumables' => ['Consumable inventory','Quantity-based supplies such as components, tools and safety materials.','Each stock item belongs to a laboratory and can optionally reference a supplier.'],
      'borrowings' => ['Equipment issue and return','Tracks requests, approvals, issue dates, due dates, returns and item condition.','Associative activity table connecting users and equipment over time.'],
      'maintenance' => ['Maintenance and calibration','Service history, reported faults, assignments, resolution, costs and calibration outcomes.','Each maintenance record belongs to one equipment item and has reporter and optional assignee user references.'],
      'transactions' => ['Inventory movement ledger','Records equipment transfers and consumable purchase, issue, return and adjustment events.','Nullable item and location references are interpreted according to transaction_type.'],
      'suppliers' => ['Supplier directory','Vendor contact, address and GST registration information.','A supplier can be referenced by many consumable stock records and purchase transactions.'],
    ];
    $orderedTables = ['departments','users','laboratories','equipment_categories','equipment','consumables','borrowings','maintenance','transactions','suppliers'];
    $columnChecks = [
      'users' => ['role'=>"admin | technician | faculty | student",'account_status'=>"active | inactive"],
      'laboratories' => ['capacity'=>"integer greater than 0"],
      'equipment' => ['purchase_price'=>"value >= 0",'status'=>"available | issued | maintenance | damaged | lost | retired",'item_condition'=>"excellent | good | fair | poor"],
      'consumables' => ['quantity_available'=>"value >= 0",'reorder_level'=>"value >= 0",'unit_price'=>"value >= 0"],
      'borrowings' => ['issue_condition'=>"excellent | good | fair | poor",'return_condition'=>"excellent | good | fair | poor; nullable",'borrowing_status'=>"pending | active | returned | rejected | overdue"],
      'maintenance' => ['maintenance_type'=>"preventive | corrective | emergency | inspection | calibration",'maintenance_cost'=>"value >= 0",'calibration_result'=>"passed | failed | conditional; nullable",'maintenance_status'=>"open | in_progress | completed | cancelled"],
      'transactions' => ['transaction_type'=>"equipment_transfer | consumable_purchase | consumable_issue | consumable_return | stock_adjustment",'quantity'=>"value > 0"],
    ];
    $relationships = [];
    foreach ($orderedTables as $tableName) {
      foreach ($pdo->query('PRAGMA foreign_key_list("' . $tableName . '")')->fetchAll() as $fk) {
        $relationships[] = ['from_table'=>$tableName,'from_column'=>$fk['from'],'to_table'=>$fk['table'],'to_column'=>$fk['to'],'on_update'=>$fk['on_update'],'on_delete'=>$fk['on_delete']];
      }
    }
    ?>
    <div class="reference-intro"><div class="reference-kicker">DATABASE DOCUMENTATION / 01</div><h2>Relational schema</h2><p>Physical SQLite schema for the LabTrace laboratory inventory and operations database. The definitions below are read directly from the active database file.</p><div class="reference-meta"><span>10 core tables</span><span><?= count($relationships) ?> foreign-key constraints</span><span>SQLite / PDO</span></div></div>
    <div class="schema-summary-grid"><div class="schema-summary"><strong>Primary keys</strong><span>Unique row identifiers</span><p>Every core entity has an integer primary key generated by SQLite.</p></div><div class="schema-summary"><strong>Foreign keys</strong><span>Referential integrity</span><p>Relationships connect departments, people, labs, assets and activity records.</p></div><div class="schema-summary"><strong>Constraints</strong><span>Data validation</span><p>UNIQUE, NOT NULL, CHECK and default rules restrict invalid records.</p></div><div class="schema-summary"><strong>Indexes</strong><span>Query support</span><p>Indexes support common lookups by status, due date, location and transaction date.</p></div></div>
    <?php foreach ($orderedTables as $tableName): $columns=$pdo->query('PRAGMA table_info("'.$tableName.'")')->fetchAll(); $fks=$pdo->query('PRAGMA foreign_key_list("'.$tableName.'")')->fetchAll(); $indexes=$pdo->query('PRAGMA index_list("'.$tableName.'")')->fetchAll(); $fkMap=[]; foreach($fks as $fk){$fkMap[$fk['from']]=$fk['table'].'.'.$fk['to'];} $uniqueCols=[]; foreach($indexes as $idx){if((int)$idx['unique']===1){foreach($pdo->query('PRAGMA index_info("'.$idx['name'].'")')->fetchAll() as $ic){$uniqueCols[$ic['name']]=true;}}} ?>
      <section class="schema-table-card" id="table-<?= e($tableName) ?>"><div class="schema-table-head"><div><div class="schema-number">TABLE <?= str_pad((string)(array_search($tableName,$orderedTables,true)+1),2,'0',STR_PAD_LEFT) ?></div><h3><?= e($tableName) ?></h3><p><?= e($tableInfo[$tableName][0]) ?> — <?= e($tableInfo[$tableName][1]) ?></p></div><span class="schema-count"><?= count($columns) ?> columns</span></div><div class="table-wrap"><table class="table schema-column-table"><thead><tr><th>Column</th><th>SQLite type</th><th>Key / rule</th><th>Null</th><th>Default</th><th>Validation / domain</th></tr></thead><tbody><?php foreach($columns as $col): $rules=[]; if((int)$col['pk']>0)$rules[]='PRIMARY KEY'; if(isset($fkMap[$col['name']]))$rules[]='FK → '.$fkMap[$col['name']]; if(isset($uniqueCols[$col['name']]))$rules[]='UNIQUE'; if((int)$col['notnull']===1)$rules[]='NOT NULL'; ?><tr><td><code><?= e($col['name']) ?></code></td><td><?= e($col['type'] ?: '—') ?></td><td><?= $rules ? e(implode(' · ',$rules)) : '<span class="text-secondary">—</span>' ?></td><td><?= (int)$col['notnull']===1 || (int)$col['pk']>0 ? 'No' : 'Yes' ?></td><td><?= $col['dflt_value']===null ? '—' : '<code>'.e($col['dflt_value']).'</code>' ?></td><td><?= isset($columnChecks[$tableName][$col['name']]) ? e($columnChecks[$tableName][$col['name']]) : '—' ?></td></tr><?php endforeach; ?></tbody></table></div><div class="schema-table-note"><strong>Design note:</strong> <?= e($tableInfo[$tableName][2]) ?><?php if($fks): ?><div class="schema-fk-list"><strong>Foreign keys:</strong> <?php foreach($fks as $fk): ?><span><?= e($fk['from']) ?> → <?= e($fk['table'].'.'.$fk['to']) ?> <small>(ON DELETE <?= e($fk['on_delete']) ?>)</small></span><?php endforeach; ?></div><?php endif; ?><?php if($indexes): ?><div class="schema-fk-list"><strong>Indexes:</strong> <?php foreach($indexes as $idx): $idxCols=array_column($pdo->query('PRAGMA index_info("'.$idx['name'].'")')->fetchAll(),'name'); ?><span><?= e($idx['name']) ?> <small>(<?= e(implode(', ',$idxCols)) ?><?= (int)$idx['unique']===1 ? '; unique' : '' ?>)</small></span><?php endforeach; ?></div><?php endif; ?></div></section>
    <?php endforeach; ?>
    <section class="schema-table-card"><div class="schema-table-head"><div><div class="schema-number">RELATIONSHIP INDEX</div><h3>Foreign-key map</h3><p>All enforced references discovered from SQLite PRAGMA metadata.</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Child table</th><th>Child column</th><th>Parent table</th><th>Parent column</th><th>On update</th><th>On delete</th></tr></thead><tbody><?php foreach($relationships as $rel): ?><tr><td><code><?= e($rel['from_table']) ?></code></td><td><?= e($rel['from_column']) ?></td><td><code><?= e($rel['to_table']) ?></code></td><td><?= e($rel['to_column']) ?></td><td><?= e($rel['on_update']) ?></td><td><?= e($rel['on_delete']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
    <section class="schema-table-card"><div class="schema-table-head"><div><div class="schema-number">NORMALIZATION NOTES</div><h3>Relational design decisions</h3><p>How the schema separates entities and prevents avoidable duplication.</p></div></div><div class="normalization-grid"><div><strong>First normal form (1NF)</strong><p>Each field stores one value per row. Repeating equipment, user or supplier details are not packed into comma-separated columns.</p></div><div><strong>Second normal form (2NF)</strong><p>Every non-key attribute describes the whole primary key. The core tables use single-column surrogate primary keys.</p></div><div><strong>Third normal form (3NF)</strong><p>Department, supplier, category and laboratory descriptions are held in their own tables and referenced by keys rather than copied into every asset or stock row.</p></div></div><p class="schema-caveat"><strong>Modelling note:</strong> <code>transactions</code> is a deliberately compact event ledger. Its nullable equipment, consumable, supplier and laboratory references are interpreted using <code>transaction_type</code>; application validation must ensure the correct combination is supplied for each event.</p></section>
<?php
 } elseif ($page === 'er_diagram') {
    $erDiagrams = [
      ['01_organisation.svg','01 · Organisation and laboratories','Departments own laboratories and users. A laboratory may have one in-charge; a user may be responsible for multiple laboratories.'],
      ['02_equipment.svg','02 · Equipment register','Equipment belongs to one category and one laboratory. Its current custodian is optional. Categories can be arranged in a parent–child hierarchy.'],
      ['03_borrowing_maintenance.svg','03 · Borrowing and maintenance','Borrowing records connect users with equipment over time. Maintenance records retain fault, service and calibration history, including reporter and assignee.'],
      ['04_consumables.svg','04 · Consumable stock','Each consumable stock record belongs to a laboratory and can reference its supplier.'],
      ['05_transactions.svg','05 · Transaction ledger','Transactions are performed by users and may reference an equipment item, consumable, source/destination laboratory or supplier depending on transaction type.'],
    ];
    ?>
    <div class="reference-intro"><div class="reference-kicker">DATABASE DOCUMENTATION / 02</div><h2>Entity–relationship diagram</h2><p>Chen-style ER diagrams for the NSUT LabTrace database. Rectangles represent entities, diamonds represent relationships and ovals represent attributes. Primary-key attributes are underlined; foreign keys are labelled FK.</p><div class="reference-meta"><span>10 entities</span><span>5 diagram views</span><span>Chen notation</span></div></div>
    <section class="schema-table-card er-card">
      <div class="schema-table-head"><div><div class="schema-number">NOTATION GUIDE</div><h3>How to read the diagrams</h3><p>These use the classic notation commonly taught in introductory DBMS courses.</p></div></div>
      <div class="chen-legend">
        <span><i class="chen-entity">Entity</i> Rectangle · Entity</span>
        <span><i class="chen-relation">Relation</i> Diamond · Relationship</span>
        <span><i class="chen-attribute">Attribute</i> Oval · Attribute</span>
        <span><u>primary_id</u> Underlined · Primary key</span>
        <span><b>(FK)</b> Foreign key</span>
      </div>
    </section>
    <?php foreach ($erDiagrams as [$file,$title,$description]): ?>
      <section class="schema-table-card er-chen-card">
        <div class="schema-table-head"><div><div class="schema-number">NSUT LABTRACE · ER MODEL</div><h3><?= e($title) ?></h3><p><?= e($description) ?></p></div></div>
        <div class="chen-diagram-wrap"><img src="assets/img/er/<?= e($file) ?>" alt="<?= e($title) ?> Chen-style entity relationship diagram" loading="lazy"></div>
      </section>
    <?php endforeach; ?>
    <section class="schema-table-card">
      <div class="schema-number">RELATIONSHIP NOTES</div><h3 class="mt-2">Important modelling details</h3>
      <div class="normalization-grid">
        <div><strong>Borrowing is an associative entity</strong><p>A user can have many borrowing records, and an equipment item can appear in many borrowing records over time. Each borrowing record refers to one user and one equipment item.</p></div>
        <div><strong>Maintenance keeps history</strong><p>An equipment item can have multiple maintenance records. Each record has one reporting user and may have one assigned technician.</p></div>
        <div><strong>Transactions use optional references</strong><p>The transaction type determines which item and location references apply. A transaction can describe an equipment transfer or a consumable stock event.</p></div>
      </div>
      <p class="schema-caveat"><strong>Note:</strong> The ER diagrams show the main conceptual relationships. The Relational Schema section contains every column, constraint, index and foreign-key rule from the actual SQLite database.</p>
    </section>
<?php
} elseif ($page === 'equipment') {
    $items=rows($pdo,"SELECT e.*,c.category_name,l.lab_name,u.full_name custodian FROM equipment e JOIN equipment_categories c ON c.category_id=e.category_id JOIN laboratories l ON l.lab_id=e.lab_id LEFT JOIN users u ON u.user_id=e.current_custodian_id ORDER BY e.created_at DESC");
    $cats=select_options($pdo,'equipment_categories','category_id','category_name'); $labs=select_options($pdo,'laboratories','lab_id','lab_name');
    ?>
    <div class="toolbar"><div><h2 class="section-heading">Equipment register</h2><div class="section-sub"><?= count($items) ?> assets in the system</div></div><div class="d-flex gap-2"><input class="form-control searchbox" placeholder="Search equipment..." data-filter-table="#equipmentTable"><?php if(in_array($user['role'],['admin','technician'],true)): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEquipment"><i class="fa fa-plus me-1"></i> Add equipment</button><?php endif; ?></div></div>
    <div class="card-panel"><div class="table-wrap"><table class="table" id="equipmentTable"><thead><tr><th>Asset tag</th><th>Equipment</th><th>Category</th><th>Laboratory</th><th>Status</th><th>Condition</th><th>Custodian</th></tr></thead><tbody><?php foreach($items as $x): ?><tr><td><strong><?= e($x['asset_tag']) ?></strong></td><td><?= e($x['equipment_name']) ?><div class="text-secondary"><?= e($x['manufacturer'].' '.$x['model']) ?></div></td><td><?= e($x['category_name']) ?></td><td><?= e($x['lab_name']) ?></td><td><span class="badge-soft <?= $x['status']==='available'?'badge-green':($x['status']==='maintenance'?'badge-orange':'badge-gray') ?>"><?= e($x['status']) ?></span></td><td><?= e($x['item_condition']) ?></td><td><?= e($x['custodian'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php if(in_array($user['role'],['admin','technician'],true)): ?>
    <div class="modal fade" id="addEquipment" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_equipment"><div class="modal-header"><h5 class="modal-title">Register equipment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
    <?php foreach(['asset_tag'=>'Asset tag','serial_number'=>'Serial number','equipment_name'=>'Equipment name','manufacturer'=>'Manufacturer','model'=>'Model','purchase_date'=>'Purchase date','purchase_price'=>'Purchase price','warranty_expiry'=>'Warranty expiry'] as $name=>$label): ?><div class="col-md-6"><label class="form-label"><?= e($label) ?></label><input class="form-control" name="<?= e($name) ?>" <?= in_array($name,['purchase_date','warranty_expiry'])?'type="date"':(in_array($name,['purchase_price'])?'type="number" step="0.01"':'') ?> <?= in_array($name,['asset_tag','equipment_name'])?'required':'' ?>></div><?php endforeach; ?>
    <div class="col-md-6"><label class="form-label">Category</label><select class="form-select" name="category_id" required><?php foreach($cats as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Laboratory</label><select class="form-select" name="lab_id" required><?php foreach($labs as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><option>available</option><option>maintenance</option><option>damaged</option></select></div><div class="col-md-6"><label class="form-label">Condition</label><select class="form-select" name="item_condition"><option>excellent</option><option selected>good</option><option>fair</option><option>poor</option></select></div>
    </div></div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save equipment</button></div></form></div></div></div><?php endif;
} elseif ($page === 'laboratories') {
    $items=rows($pdo,"SELECT l.*,d.department_name,u.full_name incharge FROM laboratories l JOIN departments d ON d.department_id=l.department_id LEFT JOIN users u ON u.user_id=l.lab_incharge_id ORDER BY l.lab_name");
    $deps=select_options($pdo,'departments','department_id','department_name'); $staff=rows($pdo,"SELECT user_id,full_name FROM users WHERE role IN ('admin','technician','faculty') AND account_status='active' ORDER BY full_name");
    ?>
    <div class="toolbar"><div><h2 class="section-heading">Laboratory directory</h2><div class="section-sub"><?= count($items) ?> registered laboratories</div></div><?php if($user['role']==='admin'): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLab"><i class="fa fa-plus me-1"></i> Add laboratory</button><?php endif; ?></div>
    <div class="row g-3"><?php foreach($items as $x): ?><div class="col-md-6 col-xl-4"><div class="card-panel h-100 mb-0"><div class="d-flex justify-content-between"><span class="stat-icon"><i class="fa-solid fa-flask"></i></span><span class="badge-soft"><?= e($x['lab_code']) ?></span></div><h3 class="section-heading mt-3"><?= e($x['lab_name']) ?></h3><div class="section-sub"><?= e($x['department_name']) ?></div><hr><div class="small text-secondary"><div><i class="fa fa-location-dot me-2"></i><?= e($x['building'].' · '.$x['room_number']) ?></div><div class="mt-2"><i class="fa fa-user me-2"></i><?= e($x['incharge'] ?? 'No incharge assigned') ?></div><div class="mt-2"><i class="fa fa-users me-2"></i>Capacity <?= e($x['capacity']) ?></div></div></div></div><?php endforeach; ?></div>
    <?php if($user['role']==='admin'): ?><div class="modal fade" id="addLab"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_lab"><div class="modal-header"><h5>Add laboratory</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
    <?php foreach(['lab_name'=>'Lab name','lab_code'=>'Lab code','building'=>'Building','room_number'=>'Room number','floor_number'=>'Floor'] as $n=>$l): ?><div class="mb-3"><label class="form-label"><?= e($l) ?></label><input class="form-control" name="<?= e($n) ?>" required></div><?php endforeach; ?>
    <div class="mb-3"><label class="form-label">Department</label><select class="form-select" name="department_id"><?php foreach($deps as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Lab incharge</label><select class="form-select" name="lab_incharge_id"><option value="">Unassigned</option><?php foreach($staff as $s): ?><option value="<?= e($s['user_id']) ?>"><?= e($s['full_name']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Capacity</label><input type="number" class="form-control" name="capacity" value="30" min="1"></div></div><div class="modal-footer"><button class="btn btn-primary">Create lab</button></div></form></div></div></div><?php endif;
} elseif ($page === 'consumables') {
    $items=rows($pdo,"SELECT c.*,l.lab_name,s.supplier_name FROM consumables c JOIN laboratories l ON l.lab_id=c.lab_id LEFT JOIN suppliers s ON s.supplier_id=c.supplier_id ORDER BY c.item_name");
    $labs=select_options($pdo,'laboratories','lab_id','lab_name'); $suppliers=select_options($pdo,'suppliers','supplier_id','supplier_name');
    ?>
    <div class="toolbar"><div><h2 class="section-heading">Consumables inventory</h2><div class="section-sub"><?= count($items) ?> stock-keeping items</div></div><div class="d-flex gap-2"><input class="form-control searchbox" placeholder="Search stock..." data-filter-table="#consumableTable"><?php if(in_array($user['role'],['admin','technician'],true)): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addConsumable"><i class="fa fa-plus me-1"></i> Add item</button><?php endif; ?></div></div>
    <div class="card-panel"><div class="table-wrap"><table class="table" id="consumableTable"><thead><tr><th>Item</th><th>Code</th><th>Laboratory</th><th>Supplier</th><th>Available</th><th>Reorder level</th><th>Stock status</th></tr></thead><tbody><?php foreach($items as $x): $low=$x['quantity_available']<=$x['reorder_level']; ?><tr><td><strong><?= e($x['item_name']) ?></strong><div class="text-secondary"><?= e($x['category']) ?></div></td><td><?= e($x['item_code']) ?></td><td><?= e($x['lab_name']) ?></td><td><?= e($x['supplier_name'] ?? '—') ?></td><td><?= e($x['quantity_available'].' '.$x['unit_of_measure']) ?></td><td><?= e($x['reorder_level']) ?></td><td><span class="badge-soft <?= $low?'badge-orange':'badge-green' ?>"><?= $low?'Reorder':'Healthy' ?></span></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php if(in_array($user['role'],['admin','technician'],true)): ?><div class="modal fade" id="addConsumable"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_consumable"><div class="modal-header"><h5>Add consumable</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
    <?php foreach(['item_name'=>'Item name','item_code'=>'Item code','category'=>'Category','quantity_available'=>'Opening quantity','unit_of_measure'=>'Unit','reorder_level'=>'Reorder level','expiry_date'=>'Expiry date','unit_price'=>'Unit price'] as $n=>$l): ?><div class="mb-3"><label class="form-label"><?= e($l) ?></label><input class="form-control" name="<?= e($n) ?>" <?= in_array($n,['quantity_available','reorder_level','unit_price'])?'type="number" step="0.001"':($n==='expiry_date'?'type="date"':'') ?> <?= in_array($n,['item_name','item_code'])?'required':'' ?>></div><?php endforeach; ?>
    <div class="mb-3"><label class="form-label">Laboratory</label><select class="form-select" name="lab_id"><?php foreach($labs as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id"><option value="">None</option><?php foreach($suppliers as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><button class="btn btn-primary">Save item</button></div></form></div></div></div><?php endif;
} elseif ($page === 'borrowings') {
    $items=rows($pdo,"SELECT b.*,u.full_name,e.asset_tag,e.equipment_name,a.full_name approver FROM borrowings b JOIN users u ON u.user_id=b.user_id JOIN equipment e ON e.equipment_id=b.equipment_id LEFT JOIN users a ON a.user_id=b.approved_by ORDER BY b.issue_date DESC");
    $assets=rows($pdo,"SELECT equipment_id,asset_tag,equipment_name FROM equipment WHERE status='available' ORDER BY equipment_name");
    ?>
    <div class="toolbar"><div><h2 class="section-heading">Borrowing desk</h2><div class="section-sub">Requests, active loans and returns</div></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#borrowEquipment"><i class="fa fa-plus me-1"></i> Request equipment</button></div>
    <div class="card-panel"><div class="table-wrap"><table class="table"><thead><tr><th>Borrower</th><th>Asset</th><th>Issue date</th><th>Due date</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($items as $x): ?><tr><td><?= e($x['full_name']) ?></td><td><strong><?= e($x['asset_tag']) ?></strong><div class="text-secondary"><?= e($x['equipment_name']) ?></div></td><td><?= e($x['issue_date']) ?></td><td><?= e($x['due_date']) ?></td><td><span class="badge-soft <?= $x['borrowing_status']==='active'?'badge-green':($x['borrowing_status']==='overdue'?'badge-red':'badge-gray') ?>"><?= e($x['borrowing_status']) ?></span></td><td><?php if($x['borrowing_status']==='pending' && in_array($user['role'],['admin','technician','faculty'],true)): ?><form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="approve_borrowing"><input type="hidden" name="borrowing_id" value="<?= e($x['borrowing_id']) ?>"><button class="btn btn-sm btn-primary">Approve</button></form><?php elseif(in_array($x['borrowing_status'],['active','overdue'],true) && in_array($user['role'],['admin','technician','faculty'],true)): ?><form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="return_borrowing"><input type="hidden" name="borrowing_id" value="<?= e($x['borrowing_id']) ?>"><select name="return_condition" class="form-select form-select-sm d-inline" style="width:110px"><option>excellent</option><option selected>good</option><option>fair</option><option>poor</option></select><button class="btn btn-sm btn-outline-primary">Return</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="modal fade" id="borrowEquipment"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="borrow_request"><div class="modal-header"><h5>Request equipment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Available equipment</label><select class="form-select" name="equipment_id" required><?php foreach($assets as $a): ?><option value="<?= e($a['equipment_id']) ?>"><?= e($a['asset_tag'].' · '.$a['equipment_name']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Due date</label><input class="form-control" type="datetime-local" name="due_date" required></div><div class="mb-3"><label class="form-label">Purpose</label><textarea class="form-control" name="remarks"></textarea></div></div><div class="modal-footer"><button class="btn btn-primary">Submit request</button></div></form></div></div></div>
<?php
} elseif ($page === 'maintenance') {
    $items=rows($pdo,"SELECT m.*,e.asset_tag,e.equipment_name,u.full_name reporter FROM maintenance m JOIN equipment e ON e.equipment_id=m.equipment_id JOIN users u ON u.user_id=m.reported_by ORDER BY m.reported_date DESC");
    $assets=select_options($pdo,'equipment','equipment_id','equipment_name'); $staff=rows($pdo,"SELECT user_id,full_name FROM users WHERE role IN ('admin','technician') AND account_status='active'");
    ?>
    <div class="toolbar"><div><h2 class="section-heading">Maintenance & calibration</h2><div class="section-sub">Repairs, inspections and service history</div></div><?php if(in_array($user['role'],['admin','technician'],true)): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMaintenance"><i class="fa fa-plus me-1"></i> Report issue</button><?php endif; ?></div>
    <div class="card-panel"><div class="table-wrap"><table class="table"><thead><tr><th>Asset</th><th>Type</th><th>Issue</th><th>Reported</th><th>Next calibration</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($items as $x): ?><tr><td><strong><?= e($x['asset_tag']) ?></strong><div class="text-secondary"><?= e($x['equipment_name']) ?></div></td><td><?= e($x['maintenance_type']) ?></td><td><?= e($x['issue_description']) ?></td><td><?= e($x['reported_date']) ?></td><td><?= e($x['next_calibration_date'] ?? '—') ?></td><td><span class="badge-soft <?= $x['maintenance_status']==='completed'?'badge-green':'badge-orange' ?>"><?= e($x['maintenance_status']) ?></span></td><td><?php if($x['maintenance_status']!=='completed' && in_array($user['role'],['admin','technician'],true)): ?><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#complete<?= e($x['maintenance_id']) ?>">Complete</button><div class="modal fade" id="complete<?= e($x['maintenance_id']) ?>"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="complete_maintenance"><input type="hidden" name="maintenance_id" value="<?= e($x['maintenance_id']) ?>"><div class="modal-header"><h5>Complete maintenance</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Resolution</label><textarea class="form-control mb-3" name="resolution" required></textarea><label class="form-label">Cost</label><input class="form-control mb-3" type="number" step="0.01" name="maintenance_cost" value="0"><label class="form-label">Calibration result</label><select class="form-select" name="calibration_result"><option value="">Not applicable</option><option>passed</option><option>failed</option><option>conditional</option></select></div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></div><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php if(in_array($user['role'],['admin','technician'],true)): ?><div class="modal fade" id="newMaintenance"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_maintenance"><div class="modal-header"><h5>Report maintenance issue</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Equipment</label><select class="form-select" name="equipment_id"><?php foreach($assets as $a): ?><option value="<?= e($a['id']) ?>"><?= e($a['label']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Type</label><select class="form-select" name="maintenance_type"><option>corrective</option><option>preventive</option><option>emergency</option><option>inspection</option><option>calibration</option></select></div><div class="mb-3"><label class="form-label">Assign technician</label><select class="form-select" name="assigned_to"><option value="">Unassigned</option><?php foreach($staff as $s): ?><option value="<?= e($s['user_id']) ?>"><?= e($s['full_name']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Issue description</label><textarea class="form-control" name="issue_description" required></textarea></div><div class="mb-3"><label class="form-label">Next calibration date</label><input class="form-control" type="date" name="next_calibration_date"></div></div><div class="modal-footer"><button class="btn btn-primary">Create record</button></div></form></div></div></div><?php endif;
} elseif ($page === 'transactions') {
    $items=rows($pdo,"SELECT t.*,u.full_name,e.asset_tag,c.item_name,sl.lab_name source_lab,dl.lab_name destination_lab,s.supplier_name FROM transactions t JOIN users u ON u.user_id=t.performed_by LEFT JOIN equipment e ON e.equipment_id=t.equipment_id LEFT JOIN consumables c ON c.consumable_id=t.consumable_id LEFT JOIN laboratories sl ON sl.lab_id=t.source_lab_id LEFT JOIN laboratories dl ON dl.lab_id=t.destination_lab_id LEFT JOIN suppliers s ON s.supplier_id=t.supplier_id ORDER BY t.transaction_date DESC LIMIT 200");
    $assets=select_options($pdo,'equipment','equipment_id','asset_tag'); $cons=select_options($pdo,'consumables','consumable_id','item_name'); $labs=select_options($pdo,'laboratories','lab_id','lab_name'); $sup=select_options($pdo,'suppliers','supplier_id','supplier_name');
    ?>
    <div class="toolbar"><div><h2 class="section-heading">Transaction ledger</h2><div class="section-sub">Equipment movement and consumable stock activity</div></div><?php if(in_array($user['role'],['admin','technician'],true)): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTransaction"><i class="fa fa-plus me-1"></i> Record transaction</button><?php endif; ?></div>
    <div class="card-panel"><div class="table-wrap"><table class="table"><thead><tr><th>ID</th><th>Type</th><th>Item</th><th>Quantity</th><th>From / To</th><th>By</th><th>Date</th></tr></thead><tbody><?php foreach($items as $x): ?><tr><td>#<?= e($x['transaction_id']) ?></td><td><?= e(str_replace('_',' ',$x['transaction_type'])) ?></td><td><?= e($x['asset_tag'] ?? $x['item_name'] ?? '—') ?></td><td><?= e($x['quantity']) ?></td><td><?= e(($x['source_lab'] ?? '—').' → '.($x['destination_lab'] ?? '—')) ?></td><td><?= e($x['full_name']) ?></td><td><?= e($x['transaction_date']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php if(in_array($user['role'],['admin','technician'],true)): ?><div class="modal fade" id="newTransaction"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="record_transaction"><div class="modal-header"><h5>Record transaction</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
    <div class="mb-3"><label class="form-label">Transaction type</label><select class="form-select" name="transaction_type"><option value="consumable_purchase">Consumable purchase / receipt</option><option value="consumable_issue">Consumable issue</option><option value="consumable_return">Consumable return</option><option value="stock_adjustment">Stock adjustment</option><option value="equipment_transfer">Equipment transfer</option></select></div>
    <div class="mb-3"><label class="form-label">Consumable (for stock transaction)</label><select class="form-select" name="consumable_id"><option value="">Not applicable</option><?php foreach($cons as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label">Equipment (for transfer)</label><select class="form-select" name="equipment_id"><option value="">Not applicable</option><?php foreach($assets as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label">Source laboratory</label><select class="form-select" name="source_lab_id"><option value="">None</option><?php foreach($labs as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label">Destination laboratory</label><select class="form-select" name="destination_lab_id"><option value="">None</option><?php foreach($labs as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id"><option value="">None</option><?php foreach($sup as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label">Quantity</label><input class="form-control" type="number" step="0.001" min="0.001" name="quantity" value="1" required></div>
    <div class="mb-3"><label class="form-label">Adjustment delta (only stock adjustment; can be negative)</label><input class="form-control" type="number" step="0.001" name="adjustment_delta" value="0"></div>
    <div class="mb-3"><label class="form-label">Reference number</label><input class="form-control" name="reference_number"></div><div class="mb-3"><label class="form-label">Remarks</label><textarea class="form-control" name="remarks"></textarea></div></div><div class="modal-footer"><button class="btn btn-primary">Save transaction</button></div></form></div></div></div><?php endif;
} elseif ($page === 'departments') {
    $items=rows($pdo,"SELECT d.*,COUNT(DISTINCT l.lab_id) lab_count,COUNT(DISTINCT u.user_id) user_count FROM departments d LEFT JOIN laboratories l ON l.department_id=d.department_id LEFT JOIN users u ON u.department_id=d.department_id GROUP BY d.department_id ORDER BY d.department_name");
    ?>
    <div class="toolbar"><div><h2 class="section-heading">Departments</h2><div class="section-sub">University academic units</div></div><?php if($user['role']==='admin'): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newDepartment">Add department</button><?php endif; ?></div><div class="card-panel"><div class="table-wrap"><table class="table"><thead><tr><th>Department</th><th>Code</th><th>Laboratories</th><th>Users</th></tr></thead><tbody><?php foreach($items as $x): ?><tr><td><?= e($x['department_name']) ?></td><td><?= e($x['department_code']) ?></td><td><?= e($x['lab_count']) ?></td><td><?= e($x['user_count']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php if($user['role']==='admin'): ?><div class="modal fade" id="newDepartment"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_department"><div class="modal-header"><h5>Add department</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="department_name" required></div><div class="mb-3"><label class="form-label">Code</label><input class="form-control" name="department_code" required></div></div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></div><?php endif;
} elseif ($page === 'categories') {
    $items=rows($pdo,"SELECT c.*,p.category_name parent_name,COUNT(e.equipment_id) equipment_count FROM equipment_categories c LEFT JOIN equipment_categories p ON p.category_id=c.parent_category_id LEFT JOIN equipment e ON e.category_id=c.category_id GROUP BY c.category_id ORDER BY c.category_name");
    $parents=select_options($pdo,'equipment_categories','category_id','category_name');
    ?>
    <div class="toolbar"><div><h2 class="section-heading">Equipment categories</h2><div class="section-sub">Organize assets into reusable categories</div></div><?php if($user['role']==='admin'): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCategory">Add category</button><?php endif; ?></div><div class="card-panel"><div class="table-wrap"><table class="table"><thead><tr><th>Category</th><th>Parent</th><th>Equipment count</th><th>Description</th></tr></thead><tbody><?php foreach($items as $x): ?><tr><td><?= e($x['category_name']) ?></td><td><?= e($x['parent_name'] ?? '—') ?></td><td><?= e($x['equipment_count']) ?></td><td><?= e($x['description']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php if($user['role']==='admin'): ?><div class="modal fade" id="newCategory"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_category"><div class="modal-header"><h5>Add category</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Category name</label><input class="form-control" name="category_name" required></div><div class="mb-3"><label class="form-label">Parent category</label><select class="form-select" name="parent_category_id"><option value="">None</option><?php foreach($parents as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description"></textarea></div></div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></div><?php endif;
} elseif ($page === 'suppliers') {
    $items=rows($pdo,"SELECT s.*,COUNT(c.consumable_id) item_count FROM suppliers s LEFT JOIN consumables c ON c.supplier_id=s.supplier_id GROUP BY s.supplier_id ORDER BY s.supplier_name");
    ?>
    <div class="toolbar"><div><h2 class="section-heading">Suppliers</h2><div class="section-sub">Approved vendors and contacts</div></div><?php if($user['role']==='admin'): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSupplier">Add supplier</button><?php endif; ?></div><div class="card-panel"><div class="table-wrap"><table class="table"><thead><tr><th>Supplier</th><th>Contact</th><th>Email</th><th>Phone</th><th>Consumables supplied</th></tr></thead><tbody><?php foreach($items as $x): ?><tr><td><strong><?= e($x['supplier_name']) ?></strong><div class="text-secondary"><?= e($x['gstin']) ?></div></td><td><?= e($x['contact_person']) ?></td><td><?= e($x['email']) ?></td><td><?= e($x['phone']) ?></td><td><?= e($x['item_count']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php if($user['role']==='admin'): ?><div class="modal fade" id="newSupplier"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_supplier"><div class="modal-header"><h5>Add supplier</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php foreach(['supplier_name'=>'Supplier name','contact_person'=>'Contact person','email'=>'Email','phone'=>'Phone','gstin'=>'GSTIN'] as $n=>$l): ?><div class="mb-3"><label class="form-label"><?= e($l) ?></label><input class="form-control" name="<?= e($n) ?>"></div><?php endforeach; ?><div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address"></textarea></div></div><div class="modal-footer"><button class="btn btn-primary">Save supplier</button></div></form></div></div></div><?php endif;
} elseif ($page === 'users') {
    require_role(['admin']);
    $items=rows($pdo,"SELECT u.*,d.department_name FROM users u LEFT JOIN departments d ON d.department_id=u.department_id ORDER BY u.created_at DESC");
    $deps=select_options($pdo,'departments','department_id','department_name');
    ?>
    <div class="toolbar"><div><h2 class="section-heading">Users & access</h2><div class="section-sub">Manage system accounts and roles</div></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newUser">Add user</button></div><div class="card-panel"><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>University ID</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th></tr></thead><tbody><?php foreach($items as $x): ?><tr><td><?= e($x['full_name']) ?></td><td><?= e($x['university_id']) ?></td><td><?= e($x['email']) ?></td><td><?= e($x['department_name'] ?? '—') ?></td><td><?= e($x['role']) ?></td><td><?= e($x['account_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="modal fade" id="newUser"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_user"><div class="modal-header"><h5>Create user</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php foreach(['university_id'=>'University ID','full_name'=>'Full name','email'=>'Email','phone'=>'Phone','password'=>'Temporary password'] as $n=>$l): ?><div class="mb-3"><label class="form-label"><?= e($l) ?></label><input class="form-control" name="<?= e($n) ?>" <?= $n==='email'?'type="email"':($n==='password'?'type="password"':'') ?> required></div><?php endforeach; ?><div class="mb-3"><label class="form-label">Department</label><select class="form-select" name="department_id"><option value="">None</option><?php foreach($deps as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role"><option>student</option><option>faculty</option><option>technician</option><option>admin</option></select></div></div><div class="modal-footer"><button class="btn btn-primary">Create account</button></div></form></div></div></div>
<?php
}
require __DIR__ . '/includes/footer.php';
