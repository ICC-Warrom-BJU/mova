<?php
/**
 * Seed data DUMMY — Master Data + Operational, penuh untuk bulan JUNI 2026.
 *
 * Jalankan SETELAH migration:  php migrations/seed_june2026.php
 *
 * Idempoten: master data pakai insertOrGetId / guard email+plat; data operasional
 * di-skip per-customer bila sudah ada trip Juni 2026. Aman dijalankan berkali-kali.
 *
 * Semua record operasional diberi created_at Juni 2026 supaya muncul di grafik
 * dashboard (grafik "Trip per bulan" mengelompokkan berdasarkan created_at).
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
mt_srand(20260601); // reproducible

echo "=== MOVA Seed — Juni 2026 (Master + Operational) ===\n\n";

/* ------------------------------------------------------------------ helpers */
function insertOrGetId(PDO $db, string $table, string $uniqueCol, $uniqueVal, array $data): int {
    $stmt = $db->prepare("SELECT id FROM `$table` WHERE `$uniqueCol` = ?");
    $stmt->execute([$uniqueVal]);
    if ($row = $stmt->fetch()) return (int)$row['id'];
    $cols = implode('`, `', array_keys($data));
    $ph = implode(', ', array_fill(0, count($data), '?'));
    $db->prepare("INSERT INTO `$table` (`$cols`) VALUES ($ph)")->execute(array_values($data));
    return (int)$db->lastInsertId();
}
function ins(PDO $db, string $table, array $data): int {
    $cols = implode('`, `', array_keys($data));
    $ph = implode(', ', array_fill(0, count($data), '?'));
    $db->prepare("INSERT INTO `$table` (`$cols`) VALUES ($ph)")->execute(array_values($data));
    return (int)$db->lastInsertId();
}
function roleId(PDO $db, string $name): int {
    static $c = [];
    if (!isset($c[$name])) { $s = $db->prepare("SELECT id FROM mova_roles WHERE name=?"); $s->execute([$name]); $c[$name] = (int)$s->fetchColumn(); }
    return $c[$name];
}
function ensureUser(PDO $db, int $roleId, ?int $customerId, string $name, string $email, string $phone): int {
    $s = $db->prepare("SELECT id FROM mova_users WHERE email=?"); $s->execute([$email]);
    if ($id = $s->fetchColumn()) return (int)$id;
    return ins($db, 'mova_users', [
        'role_id' => $roleId, 'customer_id' => $customerId, 'name' => $name, 'email' => $email,
        'password' => password_hash('demo123', PASSWORD_ARGON2ID), 'phone' => $phone, 'is_active' => 1,
    ]);
}
function pick(array $a) { return $a[array_rand($a)]; }
function jun(int $day, string $time = null): string {
    $time = $time ?? sprintf('%02d:%02d:00', mt_rand(6, 18), pick([0,15,30,45]));
    return sprintf('2026-06-%02d %s', $day, $time);
}
function slugEmail(string $code): string { return strtolower(preg_replace('/[^a-z0-9]/i', '', $code)); }

/* ------------------------------------------------------------------ pools */
$cities = ['Makassar','Parepare','Maros','Gowa','Sungguminasa','Bantaeng','Bulukumba','Sidrap','Pinrang','Barru','Pangkep','Palopo','Watampone','Sengkang','Takalar','Jeneponto'];
$purposes = ['dinas','material','karyawan','klien','lainnya'];
$fuelTypes = ['Pertalite'=>10000,'Pertamax'=>13500,'Solar'=>6800,'Dexlite'=>14500,'Pertamax Turbo'=>15000];
$stations = ['SPBU Pertamina Pettarani','SPBU Pertamina Urip Sumoharjo','SPBU Shell Panakkukang','SPBU Pertamina Parepare','SPBU Pertamina Maros','SPBU Pertamina Sungguminasa'];
$expenseCats = ['tol'=>[20000,70000],'parkir'=>[5000,25000],'retribusi'=>[10000,30000],'penyeberangan'=>[50000,150000],'makan'=>[50000,200000],'lainnya'=>[15000,80000]];
$issueCats = ['mesin','ac_kelistrikan','rem_kemudi','body','ban','lainnya'];
$issueDesc = [
    'mesin'=>'Suara mesin kasar saat idle, perlu pemeriksaan','ac_kelistrikan'=>'AC kurang dingin, kemungkinan freon berkurang',
    'rem_kemudi'=>'Rem terasa dalam, perlu penyetelan kampas','body'=>'Baret pada pintu kanan akibat gesekan','ban'=>'Ban depan kanan aus tidak merata','lainnya'=>'Wiper depan tidak berfungsi normal'];
$serviceTypes = ['Ganti Oli Mesin','Servis Berkala','Rotasi & Balancing Ban','Servis AC','Ganti Filter Udara','Tune Up Mesin','Ganti Kampas Rem'];
$workshops = ['Bengkel Resmi Toyota Makassar','Auto2000 Pettarani','Bengkel Mitra Jaya','Bengkel Karya Motor','Nasmoco Service'];
$brands = [['Toyota','Avanza','MPV'],['Toyota','Hiace','minibus'],['Mitsubishi','L300','pickup'],['Isuzu','Elf','truck'],['Daihatsu','Gran Max','pickup'],['Toyota','Innova','MPV'],['Suzuki','Carry','pickup'],['Toyota','Hilux','pickup']];
$colors = ['Putih','Hitam','Silver','Abu-abu','Merah'];
$firstNames = ['Andi','Budi','Cakra','Dedi','Eka','Fajar','Gunawan','Hasan','Irfan','Joko','Kurniawan','Lukman','Muh. Rizal','Nur','Oscar','Pratama','Rahmat','Saldi','Taufik','Usman','Wahyu','Yusuf','Zulfikar','Ilham','Reza'];
$lastNames = ['Saputra','Wijaya','Pratama','Hidayat','Nugroho','Santoso','Maulana','Ramadhan','Syahputra','Kurniawan','Setiawan','Firmansyah','Hakim','Anugrah','Mahendra'];
function personName(array $f, array $l) { return pick($f).' '.pick($l); }

/* ============================================================ 1. MASTER DATA */
echo "-- Master Data --\n";

// Regions
$rSulsel = insertOrGetId($db,'mova_regions','code','SULSEL',['name'=>'Sulawesi Selatan','code'=>'SULSEL','is_active'=>1]);
$rSulbar = insertOrGetId($db,'mova_regions','code','SULBAR',['name'=>'Sulawesi Barat','code'=>'SULBAR','is_active'=>1]);

// Branches
$branches = [];
$branchDefs = [
    ['MKS','Makassar',$rSulsel,'Jl. Sultan Hasanuddin No. 10, Makassar','0411-123456'],
    ['PARE','Parepare',$rSulsel,'Jl. Bau Massepe No. 45, Parepare','0421-223344'],
    ['GOWA','Gowa',$rSulsel,'Jl. Sultan Hasanuddin No. 88, Sungguminasa','0411-880011'],
    ['MRS','Maros',$rSulsel,'Jl. Poros Makassar-Maros KM 20, Maros','0411-371122'],
    ['BONE','Bone',$rSulsel,'Jl. Ahmad Yani No. 12, Watampone','0481-334455'],
    ['MMU','Mamuju',$rSulbar,'Jl. Yos Sudarso No. 7, Mamuju','0426-221100'],
];
foreach ($branchDefs as [$code,$name,$rid,$addr,$phone]) {
    $branches[$code] = insertOrGetId($db,'mova_branches','code',$code,['region_id'=>$rid,'name'=>$name,'code'=>$code,'address'=>$addr,'phone'=>$phone,'is_active'=>1]);
}
echo "   Regions: 2, Branches: ".count($branches)."\n";

// Plans: 1=free 2=premium 3=enterprise (dari migration 001)
$plan = ['free'=>1,'premium'=>2,'enterprise'=>3];

// Customers (DEMO01 sudah ada dari seed.php; sisanya baru)
$customerDefs = [
    ['DEMO01','PT. Transportasi Demo','MKS','free'],
    ['MTL-MKS','PT. Mitra Trans Logistik','MKS','premium'],
    ['SRP-PARE','CV. Sinar Rejeki Parepare','PARE','free'],
    ['GWT-GOWA','PT. Gowa Wisata Transport','GOWA','enterprise'],
    ['BRA-MRS','PT. Berkah Rental Armada','MRS','premium'],
];
$customers = [];
foreach ($customerDefs as $i => [$code,$name,$brCode,$planName]) {
    $cid = insertOrGetId($db,'mova_customers','code',$code,[
        'branch_id'=>$branches[$brCode], 'subscription_plan_id'=>$plan[$planName], 'name'=>$name, 'code'=>$code,
        'pic_name'=>personName($firstNames,$lastNames), 'pic_phone'=>'08'.mt_rand(1200000000,1399999999),
        'pic_email'=>'pic.'.slugEmail($code).'@demo.com', 'contract_start'=>'2026-01-01','contract_end'=>'2026-12-31',
        'total_units'=>5, 'is_active'=>1,
    ]);
    $db->prepare("INSERT IGNORE INTO mova_customer_configs (customer_id, enable_supervisor_approval) VALUES (?, ?)")
       ->execute([$cid, $planName==='enterprise'?1:0]);
    $customers[$code] = ['id'=>$cid,'name'=>$name,'plan'=>$planName,'branch'=>$brCode];
}
echo "   Customers: ".count($customers)."\n";

// Users per customer + vehicles
foreach ($customers as $code => &$c) {
    $cid = $c['id']; $slug = slugEmail($code);
    if ($code === 'DEMO01') {
        // pakai user eksisting dari seed.php
        $c['manager'] = (int)$db->query("SELECT id FROM mova_users WHERE email='manager@demo.com'")->fetchColumn();
        $c['koord']   = (int)$db->query("SELECT id FROM mova_users WHERE email='koord@demo.com'")->fetchColumn();
        $d1 = (int)$db->query("SELECT id FROM mova_users WHERE email='driver@demo.com'")->fetchColumn();
        $d2 = ensureUser($db, roleId($db,'driver'), $cid, personName($firstNames,$lastNames), 'driver2.demo01@demo.com','08'.mt_rand(1200000000,1399999999));
        $c['drivers'] = [$d1,$d2];
    } else {
        $c['manager'] = ensureUser($db, roleId($db,'manager'),      $cid, personName($firstNames,$lastNames), "manager.$slug@demo.com", '08'.mt_rand(1200000000,1399999999));
        $c['koord']   = ensureUser($db, roleId($db,'koordinator'),   $cid, personName($firstNames,$lastNames), "koord.$slug@demo.com",   '08'.mt_rand(1200000000,1399999999));
        $c['drivers'] = [
            ensureUser($db, roleId($db,'driver'), $cid, personName($firstNames,$lastNames), "driver1.$slug@demo.com", '08'.mt_rand(1200000000,1399999999)),
            ensureUser($db, roleId($db,'driver'), $cid, personName($firstNames,$lastNames), "driver2.$slug@demo.com", '08'.mt_rand(1200000000,1399999999)),
        ];
    }

    // Vehicles (5/customer). DEMO01 sudah punya 'B 1234 XX'.
    $c['vehicles'] = [];
    if ($code === 'DEMO01') {
        $vid = (int)$db->query("SELECT id FROM mova_vehicles WHERE plate_number='B 1234 XX'")->fetchColumn();
        if ($vid) $c['vehicles'][] = $vid;
    }
    $need = 5 - count($c['vehicles']);
    for ($k = 0; $k < $need; $k++) {
        [$brand,$model,$type] = pick($brands);
        $plate = sprintf('DD %d %s', mt_rand(1000,9999), chr(mt_rand(65,90)).chr(mt_rand(65,90)));
        // pastikan unik
        while ($db->query("SELECT 1 FROM mova_vehicles WHERE plate_number=".$db->quote($plate))->fetchColumn()) {
            $plate = sprintf('DD %d %s', mt_rand(1000,9999), chr(mt_rand(65,90)).chr(mt_rand(65,90)));
        }
        $c['vehicles'][] = ins($db,'mova_vehicles',[
            'customer_id'=>$cid,'plate_number'=>$plate,'brand'=>$brand,'model'=>$model,'year'=>mt_rand(2018,2024),
            'color'=>pick($colors),'vehicle_type'=>$type,'current_km'=>mt_rand(20000,90000),'status'=>'ready',
            'stnk_expiry'=>sprintf('2026-%02d-%02d',mt_rand(7,12),mt_rand(1,28)),'kir_expiry'=>sprintf('2026-%02d-%02d',mt_rand(7,12),mt_rand(1,28)),'is_active'=>1,
        ]);
    }
    // set total_units sesuai jumlah kendaraan
    $db->prepare("UPDATE mova_customers SET total_units=? WHERE id=?")->execute([count($c['vehicles']), $cid]);
}
unset($c);
echo "   Users & Vehicles per customer: OK\n";

// Company users branch access (operation & marketing -> semua branch, biar bisa lihat data demo)
foreach (['operation@mova.com','marketing@mova.com'] as $em) {
    $uid = (int)($db->query("SELECT id FROM mova_users WHERE email=".$db->quote($em))->fetchColumn() ?: 0);
    if ($uid) foreach ($branches as $bid) {
        $db->prepare("INSERT IGNORE INTO mova_user_branch_access (user_id, branch_id) VALUES (?, ?)")->execute([$uid,$bid]);
    }
}

/* ==================================================== 2. OPERATIONAL — JUNI 2026 */
echo "\n-- Operational (Juni 2026) --\n";

$seqReq = 1; $seqTrip = 1; $seqIss = 1;
$tot = ['req'=>0,'trip'=>0,'fuel'=>0,'exp'=>0,'chk'=>0,'iss'=>0,'ms'=>0,'ml'=>0,'notif'=>0];
$odo = []; // odometer per vehicle

foreach ($customers as $code => $c) {
    $cid = $c['id'];
    // guard: sudah ada trip Juni 2026?
    $g = $db->prepare("SELECT COUNT(*) FROM mova_trips WHERE customer_id=? AND trip_date BETWEEN '2026-06-01' AND '2026-06-30'");
    $g->execute([$cid]);
    if ($g->fetchColumn() > 0) { echo "   [SKIP] $code sudah punya data Juni\n"; continue; }

    $koord = $c['koord']; $drivers = $c['drivers']; $vehicles = $c['vehicles'];
    foreach ($vehicles as $v) { if (!isset($odo[$v])) $odo[$v] = (int)$db->query("SELECT current_km FROM mova_vehicles WHERE id=$v")->fetchColumn(); }

    /* ---- Vehicle Requests (8-12) ---- */
    $nReq = mt_rand(8,12);
    for ($i=0;$i<$nReq;$i++) {
        $day = mt_rand(1,30); $dur = mt_rand(0,2);
        $dep = sprintf('2026-06-%02d',$day); $ret = date('Y-m-d', strtotime("$dep +$dur day"));
        $reqBy = pick($drivers);
        $rStatus = pick(['approved','approved','approved','pending','rejected','approved_l1','cancelled']);
        $driverOpt = pick(['with_driver','with_driver','with_driver','without_driver']);
        $durType = pick(['full_day','full_day','full_day','half_day']);
        $row = [
            'customer_id'=>$cid,'request_number'=>sprintf('REQ-202606-%04d',$seqReq++),'requested_by'=>$reqBy,
            'department'=>pick(['Operasional','Logistik','Umum','Marketing','Produksi']),
            'origin'=>'Makassar','destination'=>pick($cities),'purpose'=>pick(['Kunjungan klien','Pengantaran material','Antar-jemput karyawan','Dinas luar kota','Survei lokasi']),
            'driver_option'=>$driverOpt,'duration_type'=>$durType,
            'departure_date'=>$dep,'return_date'=>($durType==='half_day'?$dep:$ret),
            'start_time'=>($durType==='half_day'?'08:00:00':null),'end_time'=>($durType==='half_day'?pick(['12:00:00','13:00:00','16:00:00']):null),
            'passenger_count'=>mt_rand(1,6),
            'vehicle_preference'=>pick(['MPV','Pickup','Minibus','Box']),'status'=>$rStatus,'created_at'=>jun($day),
        ];
        if (in_array($rStatus,['approved','approved_l1'])) {
            $row['assigned_vehicle_id']=pick($vehicles); $row['assigned_driver_id']=pick($drivers);
            $row['approved_by_l1']=$koord; $row['approved_at_l1']=jun($day,'09:00:00');
        } elseif ($rStatus==='rejected') {
            $row['rejected_by']=$koord; $row['rejection_reason']=pick(['Kendaraan tidak tersedia','Jadwal bentrok','Tujuan di luar area layanan']);
        }
        ins($db,'mova_vehicle_requests',$row); $tot['req']++;
    }

    /* ---- Trips (per hari, ~1-2) + turunannya ---- */
    for ($day=1;$day<=30;$day++) {
        $nTrip = (mt_rand(1,100) <= 78) ? mt_rand(1,2) : 0; // ~78% hari ada trip
        for ($t=0;$t<$nTrip;$t++) {
            $veh = pick($vehicles); $drv = pick($drivers);
            $st = pick(['completed','completed','completed','completed','completed','completed','in_progress','cancelled','draft']);
            $origin = 'Makassar'; $dest = pick($cities);
            $depT = sprintf('%02d:%02d:00', mt_rand(6,10), pick([0,15,30]));
            $trip = [
                'customer_id'=>$cid,'vehicle_id'=>$veh,'driver_id'=>$drv,'trip_number'=>sprintf('TRP-202606-%04d',$seqTrip++),
                'origin'=>$origin,'destination'=>$dest,'trip_date'=>sprintf('2026-06-%02d',$day),'departure_time'=>$depT,
                'purpose_type'=>pick($purposes),'status'=>$st,'input_by'=>$koord,'created_at'=>jun($day,$depT),
                'notes'=>pick(['','','Perjalanan lancar','Muatan penuh','Klien puas']),
            ];
            if ($st==='completed') {
                $dist = mt_rand(15,480); $ks=$odo[$veh]; $ke=$ks+$dist; $odo[$veh]=$ke;
                $trip['km_start']=$ks; $trip['km_end']=$ke; $trip['distance_km']=$dist;
                $trip['return_time']=sprintf('%02d:%02d:00', min(23,(int)substr($depT,0,2)+mt_rand(1,8)), pick([0,15,30]));
            }
            $tid = ins($db,'mova_trips',$trip); $tot['trip']++;

            if ($st!=='completed') continue;

            // Checklist pre+post (~70%)
            if (mt_rand(1,100)<=70) {
                $pre = json_encode([
                    ['name'=>'Ban & Tekanan Angin','status'=>'ok','note'=>'Normal 32 psi'],
                    ['name'=>'Oli Mesin','status'=>'ok','note'=>'Level normal'],
                    ['name'=>'Air Radiator','status'=>'ok','note'=>'Cukup'],
                    ['name'=>'Lampu & Sein','status'=>'ok','note'=>'Berfungsi'],
                    ['name'=>'Rem','status'=>'ok','note'=>'Baik'],
                ], JSON_UNESCAPED_UNICODE);
                $cond = pick(['good','good','good','minor_issue']);
                ins($db,'mova_trip_checklists',['trip_id'=>$tid,'check_type'=>'pre_trip','submitted_by'=>$drv,'submitted_at'=>jun($day,$depT),'items'=>$pre,'overall_condition'=>$cond,'notes'=>'Kendaraan siap jalan','created_at'=>jun($day,$depT)]);
                $post = json_encode([
                    ['name'=>'Kondisi Kendaraan','status'=>'ok','note'=>'Baik'],
                    ['name'=>'Kebersihan Interior','status'=>'ok','note'=>'Bersih'],
                    ['name'=>'BBM Akhir','status'=>'ok','note'=>'Terisi'],
                ], JSON_UNESCAPED_UNICODE);
                ins($db,'mova_trip_checklists',['trip_id'=>$tid,'check_type'=>'post_trip','submitted_by'=>$drv,'submitted_at'=>jun($day,'17:00:00'),'items'=>$post,'overall_condition'=>'good','notes'=>'Perjalanan selesai','created_at'=>jun($day,'17:00:00')]);
                $tot['chk']+=2;
            }

            // Fuel (~60%)
            if (mt_rand(1,100)<=60) {
                $ft = array_rand($fuelTypes); $ppl=$fuelTypes[$ft]; $lt=mt_rand(20,60);
                $fStatus = pick(['approved','approved','approved','pending','rejected']);
                $frow = ['customer_id'=>$cid,'trip_id'=>$tid,'vehicle_id'=>$veh,'reported_by'=>$drv,'fuel_date'=>sprintf('2026-06-%02d',$day),
                    'fuel_type'=>$ft,'liters'=>$lt,'price_per_liter'=>$ppl,'total_cost'=>$lt*$ppl,
                    'km_at_refuel'=>($trip['km_start']??0)+mt_rand(0,($trip['distance_km']??50)),'station_name'=>pick($stations),'status'=>$fStatus,'created_at'=>jun($day)];
                if ($fStatus==='approved'){ $frow['approved_by']=$koord; $frow['approved_at']=jun($day,'18:00:00'); }
                elseif ($fStatus==='rejected'){ $frow['rejection_reason']='Nota tidak jelas / tidak sesuai'; }
                ins($db,'mova_fuel_reports',$frow); $tot['fuel']++;
            }

            // Expense (~50%, 1-2 baris)
            if (mt_rand(1,100)<=50) {
                $nE = mt_rand(1,2);
                for ($e=0;$e<$nE;$e++) {
                    $cat = array_rand($expenseCats); [$lo,$hi]=$expenseCats[$cat];
                    $eStatus = pick(['approved','approved','pending','rejected']);
                    $erow = ['customer_id'=>$cid,'trip_id'=>$tid,'vehicle_id'=>$veh,'reported_by'=>$drv,'expense_date'=>sprintf('2026-06-%02d',$day),
                        'category'=>$cat,'description'=>ucfirst($cat).' '.$dest,'amount'=>mt_rand($lo,$hi),'status'=>$eStatus,'created_at'=>jun($day)];
                    if ($eStatus==='approved'){ $erow['approved_by']=$koord; $erow['approved_at']=jun($day,'18:30:00'); }
                    elseif ($eStatus==='rejected'){ $erow['rejection_reason']='Melebihi plafon / tanpa bukti'; }
                    ins($db,'mova_expense_reports',$erow); $tot['exp']++;
                }
            }
        }
    }

    /* ---- Issue Reports (3-5) ---- */
    $nIss = mt_rand(3,5);
    for ($i=0;$i<$nIss;$i++) {
        $day=mt_rand(1,30); $cat=pick($issueCats); $sev=pick(['low','medium','medium','high','critical']);
        $stat=pick(['open','open','in_review','in_progress','resolved','closed']);
        $row=['customer_id'=>$cid,'vehicle_id'=>pick($vehicles),'report_number'=>sprintf('ISS-202606-%04d',$seqIss++),
            'reported_by'=>pick($drivers),'category'=>$cat,'description'=>$issueDesc[$cat],'severity'=>$sev,'status'=>$stat,'created_at'=>jun($day)];
        if (in_array($stat,['resolved','closed'])){ $row['resolved_at']=jun(min(30,$day+mt_rand(1,5)),'15:00:00'); $row['resolved_notes']='Sudah diperbaiki di bengkel'; }
        ins($db,'mova_issue_reports',$row); $tot['iss']++;
    }

    /* ---- Maintenance Schedules (3) + Logs (2) ---- */
    $msDefs = [
        ['km_based','Ganti Oli Mesin', 'scheduled'],
        ['date_based','Servis AC', 'overdue'],
        ['km_based','Servis Berkala', 'completed'],
    ];
    foreach ($msDefs as [$trig,$svc,$msStatus]) {
        $veh = pick($vehicles);
        $row = ['customer_id'=>$cid,'vehicle_id'=>$veh,'service_type'=>$svc,'trigger_type'=>$trig,'reminder_days_before'=>pick([7,14]),'status'=>$msStatus,'created_by'=>$koord,'created_at'=>jun(mt_rand(1,15))];
        if ($trig==='km_based') $row['km_threshold'] = (($odo[$veh] ?? 50000) + mt_rand(500,5000));
        else $row['scheduled_date'] = sprintf('2026-06-%02d', mt_rand(15,30));
        ins($db,'mova_maintenance_schedules',$row); $tot['ms']++;
    }
    for ($i=0;$i<2;$i++) {
        $veh=pick($vehicles); $day=mt_rand(1,28); $svc=pick($serviceTypes); $kms=$odo[$veh] ?? mt_rand(30000,90000);
        ins($db,'mova_maintenance_logs',['customer_id'=>$cid,'vehicle_id'=>$veh,'service_type'=>$svc,'service_date'=>sprintf('2026-06-%02d',$day),
            'km_at_service'=>$kms,'workshop_name'=>pick($workshops),'cost'=>mt_rand(150000,2500000),'notes'=>'Servis rutin',
            'next_service_km'=>$kms+5000,'next_service_date'=>date('Y-m-d',strtotime(sprintf('2026-06-%02d',$day).' +3 month')),'logged_by'=>$koord,'created_at'=>jun($day)]);
        $tot['ml']++;
    }

    /* ---- Notifications (manager & koord) ---- */
    foreach ([$c['manager'],$koord] as $uid) {
        for ($i=0;$i<3;$i++) {
            $day=mt_rand(20,30); $type=pick(['vehicle_request','fuel_report','issue','maintenance','trip']);
            $titles=['vehicle_request'=>'Request kendaraan baru','fuel_report'=>'Laporan BBM menunggu approval','issue'=>'Laporan kerusakan kendaraan','maintenance'=>'Pengingat jadwal servis','trip'=>'Trip selesai'];
            ins($db,'mova_notifications',['user_id'=>$uid,'customer_id'=>$cid,'type'=>$type,'title'=>$titles[$type],
                'message'=>$titles[$type].' — perlu ditinjau.','channel'=>'in_app','is_read'=>pick([0,0,1]),'created_at'=>jun($day)]);
            $tot['notif']++;
        }
    }

    // update odometer kendaraan
    foreach ($vehicles as $v) $db->prepare("UPDATE mova_vehicles SET current_km=? WHERE id=?")->execute([$odo[$v], $v]);

    echo "   [OK] $code — requests, trips, fuel, expense, checklist, issue, maintenance, notif\n";
}

/* ------------------------------------------------------------------ ringkasan */
echo "\n=== Ringkasan yang dibuat (run ini) ===\n";
printf("  Vehicle Requests : %d\n", $tot['req']);
printf("  Trips            : %d\n", $tot['trip']);
printf("  Fuel Reports     : %d\n", $tot['fuel']);
printf("  Expense Reports  : %d\n", $tot['exp']);
printf("  Checklists       : %d\n", $tot['chk']);
printf("  Issue Reports    : %d\n", $tot['iss']);
printf("  Maint. Schedules : %d\n", $tot['ms']);
printf("  Maint. Logs      : %d\n", $tot['ml']);
printf("  Notifications    : %d\n", $tot['notif']);

echo "\n=== Total di database ===\n";
foreach ([
    'mova_regions','mova_branches','mova_customers','mova_users','mova_vehicles',
    'mova_vehicle_requests','mova_trips','mova_fuel_reports','mova_expense_reports',
    'mova_trip_checklists','mova_issue_reports','mova_maintenance_schedules','mova_maintenance_logs','mova_notifications',
] as $tb) {
    printf("  %-28s %d\n", $tb, (int)$db->query("SELECT COUNT(*) FROM `$tb`")->fetchColumn());
}
echo "\nSelesai. Login admin@mova.com / admin123 lalu filter Juni 2026.\n";
