<?php
function handle_action(string $action): void {
    HttpSecurity::requireCsrf();
    shop_require_action($action);
    if (function_exists('handle_vps_export_action') && handle_vps_export_action($action)) return;
    if (handle_account_deletion_action($action)) return;
    if (handle_notification_action($action)) return;
    if (handle_order_admin_action($action)) return;
    if (handle_promotion_action($action)) return;
    if (function_exists('handle_hosting_domain_action') && handle_hosting_domain_action($action)) return;
    if (handle_infrastructure_action($action)) return;
    if (handle_shop_option_action($action)) return;
    if (handle_domain_action($action)) return;
    if (handle_contract_action($action)) return;
    if (handle_commerce_action($action)) return;
    if (handle_integration_action($action)) return;
    switch ($action) {
        case 'appearance':
            $theme=save_appearance((string)($_POST['scope']??''),$_POST['theme']??null);
            if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){header('Content-Type: application/json');echo json_encode(['success'=>true,'theme'=>$theme]);exit;}
            flash('Deine Darstellung wurde gespeichert.');
            $target=$_SERVER['REQUEST_URI']??'/';
            redirect(str_starts_with($target,'/') && !str_starts_with($target,'//') && !preg_match('/[\r\n]/',$target)?$target:'/');
        case 'register':
            rate_limit('register', 6, 3600);
            if (!empty($_POST['website'])) throw new RuntimeException('Die Registrierung konnte nicht abgeschlossen werden.');
            $first = text_field('first_name',100); $last = text_field('last_name',100);
            $email = strtolower(text_field('email')); $password = (string)($_POST['password'] ?? '');
            if (!$first || !$last || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Bitte vervollständige deinen Namen und eine gültige E-Mail-Adresse.');
            if (strlen($password) < 12 || strlen($password) > 200) throw new RuntimeException('Dein Passwort muss 12 bis 200 Zeichen lang sein.');
            if ($password !== ($_POST['password_confirm'] ?? '')) throw new RuntimeException('Die Passwörter stimmen nicht überein.');
            if (!isset($_POST['privacy'])) throw new RuntimeException('Bitte bestätige den Datenschutzhinweis.');
            require_once dirname(__DIR__,3).'/src/core/RegistrationService.php';
            $result=(new RegistrationService(db()))->register(['first_name'=>$first,'last_name'=>$last,'email'=>$email,'password'=>$password,'confirm_password'=>$_POST['password_confirm']??'','accept_terms'=>isset($_POST['privacy'])]);
            if(!$result['success'])throw new RuntimeException($result['message']);
            flash($result['message']);redirect('/login');
        case 'login':
        case 'admin_login':
            rate_limit($action, 8, 900);
            $email = strtolower(text_field('email')); $password = (string)($_POST['password'] ?? '');
            $person = $action === 'admin_login'
                ? one("SELECT * FROM users WHERE (email = ? OR username = ?) AND active = 'y' AND role = 'admin'", [$email,$email])
                : one("SELECT * FROM customers WHERE email = ? AND status = 'active'", [$email]);
            $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
            if (!password_verify($password, $person['password_hash'] ?? $dummy) || !$person) throw new RuntimeException('E-Mail-Adresse oder Passwort ist nicht korrekt.');
            if ($action === 'admin_login') {
                Identity::login('backend',$person);
                query('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?',[$person['id']]); redirect('/admin');
            }
            login_customer($person); return_after_login();
        case 'logout':
            Identity::logout();redirect('/');
        case 'request':
            $product = product(text_field('product',80));
            if (!$product) throw new RuntimeException('Dieses Produkt ist derzeit nicht verfügbar.');
            $domain = valid_domain(text_field('domain',253)); $notes = text_field('notes',5000);
            if ($product['category'] === 'domain' && !$domain) throw new RuntimeException('Bitte gib deine Wunschdomain ein.');
            if($product['category']==='domain' && $domain && domain_catalog_managed())$product=domain_select_product($product,$domain);
            $configured=configured_product($product,$_POST['modules']??[]);
            $configuration = [];if(isset($configured['_module_selection']))$configuration['module_quantities']=$_POST['modules']??[];if(isset($configured['_module_selection']))$configuration['module_configuration']=['lines'=>$configured['_module_selection'],'profile'=>checkout_automation($configured)['settings'],'net_cents'=>product_options($configured)['net_cents']];if($product['category']==='domain'){$intent=(string)($_POST['domain_intent']??'registration');if(!in_array($intent,['registration','transfer'],true))throw new RuntimeException('Bitte Registrierung oder Domainumzug auswählen.');$configuration['domain_intent']=$intent;if($intent==='transfer'){$product['name']='Domainumzug '.$domain;$product['price_cents']=null;}}
            foreach (['cpu'=>['nach-beratung','2','4','8','16'],'ram'=>['nach-beratung','2','4','8','16','32'],'storage'=>['nach-beratung','20','50','100','250','500'],'os'=>['nach-beratung','debian','ubuntu','windows']] as $key => $allowed) {
                $value = text_field($key,30);
                if ($value && !in_array($value,$allowed,true)) throw new RuntimeException('Bitte wähle eine gültige Konfiguration.');
                if ($value) $configuration[$key] = $value;
            }
            if(strlen(json_encode($configuration))>60000)throw new RuntimeException('Die Konfiguration ist zu umfangreich. Bitte die Modulbeschreibungen kürzen.');
            if (!user()) {
                $_SESSION['draft'] = ['slug'=>$product['slug'],'domain'=>$domain,'notes'=>$notes,'configuration'=>$configuration];
                $_SESSION['after_login'] = '/konfigurieren?produkt='.rawurlencode($product['slug']);
                redirect('/login');
            }
            $customer = require_user(); rate_limit('request',12,3600);
            $reference = 'OC-' . date('ym') . '-' . strtoupper(bin2hex(random_bytes(3)));
            db()->beginTransaction();try{
                account_active_customer_lock((int)$customer['id']);
                query('INSERT INTO shop_requests (reference,customer_id,product_slug,product_name,price_cents,billing,domain,configuration,notes) VALUES (?,?,?,?,?,?,?,?,?)', [$reference,$customer['id'],$product['slug'],$product['name'],$product['price_cents'],$product['billing'],$domain,json_encode($configuration),$notes]);
                db()->commit();
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
            unset($_SESSION['draft']);
            flash('Deine Anfrage '.$reference.' ist eingegangen. Du findest sie jetzt in deinem Kundenbereich.'); redirect('/konto/anfragen');
        case 'cancel_request':
            $customer = require_user();
            if(one("SELECT id FROM shop_quotes WHERE request_id=? AND customer_id=? AND order_id IS NOT NULL",[(int)($_POST['id']??0),$customer['id']]))throw new RuntimeException('Zu dieser Anfrage besteht bereits eine Bestellung. Bitte kontaktiere den Support.');
            query("UPDATE shop_requests SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND customer_id = ? AND status IN ('new','reviewing','quoted')",[(int)($_POST['id']??0),$customer['id']]);
            query("UPDATE shop_quotes SET status='cancelled' WHERE request_id=? AND customer_id=? AND order_id IS NULL AND status IN ('sent','draft')",[(int)($_POST['id']??0),$customer['id']]);
            flash('Die Anfrage wurde zurückgezogen.'); redirect('/konto/anfragen');
        case 'ticket':
            $customer = require_user(); rate_limit('ticket',10,3600);
            $subject = text_field('subject',200); $message = text_field('message',12000);
            $category = text_field('category',30); $priority = text_field('priority',20);
            if (!$subject || !$message) throw new RuntimeException('Bitte trage einen Betreff und deine Nachricht ein.');
            if (!in_array($category,['technical','billing','general','account'],true) || !in_array($priority,['low','medium','high'],true)) throw new RuntimeException('Bitte wähle gültige Ticket-Einstellungen.');
            $number = 'T-' . strtoupper(bin2hex(random_bytes(5)));
            db()->beginTransaction();try{
                $customer=account_active_customer_lock((int)$customer['id']);
                query('INSERT INTO support_tickets (ticket_number,customer_id,subject,message,email,customer_name,category,priority) VALUES (?,?,?,?,?,?,?,?)',[$number,$customer['id'],$subject,$message,$customer['email'],$customer['first_name'].' '.$customer['last_name'],$category,$priority]);
                $id = db()->lastInsertId();db()->commit();
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
            flash('Dein Ticket wurde erstellt. Wir kümmern uns um dein Anliegen.'); redirect('/konto/ticket?id='.$id);
        case 'reply':
        case 'admin_reply':
            $isAdmin = $action === 'admin_reply';
            $person = $isAdmin ? require_admin() : require_user();
            rate_limit($action,30,3600);
            $internal=$isAdmin && ($_POST['internal']??'')==='1';
            $id = (int)($_POST['id']??0);
            $ticket = $isAdmin ? one('SELECT * FROM support_tickets WHERE id = ?',[$id]) : one('SELECT * FROM support_tickets WHERE id = ? AND customer_id = ?',[$id,$person['id']]);
            if (!$ticket) throw new RuntimeException('Dieses Ticket ist nicht verfügbar.');
            $message = text_field('message',12000);
            if (!$message) throw new RuntimeException('Bitte gib eine Nachricht ein.');
            db()->beginTransaction();
            try {
                if(!$isAdmin)account_active_customer_lock((int)$person['id']);
                query('INSERT INTO ticket_replies (ticket_id,customer_id,admin_id,message,is_internal) VALUES (?,?,?,?,?)',[$id,$isAdmin?null:$person['id'],$isAdmin?$person['id']:null,$message,$internal?1:0]);
                if(!$internal)query('UPDATE support_tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',[$isAdmin?'waiting_customer':'waiting_admin',$id]);
                db()->commit();
            } catch (Throwable $e) { db()->rollBack(); throw $e; }
            flash('Deine Antwort wurde gespeichert.'); redirect(($isAdmin?'/admin/ticket':'/konto/ticket').'?id='.$id);
        case 'profile':
            $customer = require_user();
            $first = text_field('first_name',100); $last = text_field('last_name',100);
            if (!$first || !$last) throw new RuntimeException('Bitte gib deinen Vor- und Nachnamen ein.');
            $billing=billing_input($_POST);
            query('UPDATE customers SET first_name=?,last_name=?,full_name=?,company=?,phone=?,address=?,postal_code=?,city=?,country=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status=\'active\'',[$first,$last,$first.' '.$last,$billing['company'],$billing['phone'],$billing['address'],$billing['postal_code'],$billing['city'],$billing['country'],$customer['id']]);
            flash('Deine Kontaktdaten wurden aktualisiert.'); redirect('/konto/einstellungen');
        case 'password':
        case 'admin_password':
            $isAdmin = $action === 'admin_password'; $person = $isAdmin ? require_admin() : require_user();
            rate_limit($action,6,900);
            if (!password_verify((string)($_POST['current_password']??''),$person['password_hash'])) throw new RuntimeException('Das aktuelle Passwort ist nicht korrekt.');
            $new = (string)($_POST['password']??'');
            if (mb_strlen($new)<12 || strlen($new)>200) throw new RuntimeException('Das neue Passwort muss 12 bis 200 Zeichen lang sein.');
            if ($new !== ($_POST['password_confirm']??'')) throw new RuntimeException('Die neuen Passwörter stimmen nicht überein.');
            $table = $isAdmin?'users':'customers';
            query("UPDATE $table SET password_hash=? WHERE id=?".($isAdmin?"":" AND status='active'"),[password_hash($new,PASSWORD_DEFAULT),$person['id']]);
            session_regenerate_id(true); flash('Dein Passwort wurde geändert.'); redirect($isAdmin?'/admin/einstellungen':'/konto/einstellungen');
        case 'admin_request':
            require_admin();
            $status = text_field('status',25);
            if (!in_array($status,['new','reviewing','quoted','completed','declined','cancelled'],true)) throw new RuntimeException('Ungültiger Status.');
            query('UPDATE shop_requests SET status=?,admin_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,text_field('admin_note',10000),(int)($_POST['id']??0)]);
            flash('Die Anfrage wurde aktualisiert.'); redirect('/admin/anfragen?id='.(int)$_POST['id']);
        case 'admin_ticket_status':
            require_admin(); $status = text_field('status',25);
            if (!in_array($status,['open','in_progress','waiting_customer','waiting_admin','resolved','closed'],true)) throw new RuntimeException('Ungültiger Status.');
            query('UPDATE support_tickets SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,(int)($_POST['id']??0)]);
            flash('Der Ticketstatus wurde aktualisiert.'); redirect('/admin/ticket?id='.(int)$_POST['id']);
        case 'admin_product':
            require_admin(); $name=text_field('name',120); $slug=text_field('slug',80); $category=text_field('category',40);
            if (!$name || !preg_match('/^[a-z0-9-]{2,80}$/D',$slug) || !in_array($category,['hosting','server','domain','managed'],true)) throw new RuntimeException('Bitte prüfe Produktname, URL-Kürzel und Kategorie.');
            $billing=text_field('billing',20); if (!in_array($billing,['month','quarter','halfyear','year','once'],true)) throw new RuntimeException('Bitte wähle einen gültigen Abrechnungszeitraum.');
            $inputPrice=str_replace(',','.',text_field('price',20));
            if ($inputPrice!=='' && (!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/D',$inputPrice))) throw new RuntimeException('Bitte gib einen gültigen Preis mit höchstens zwei Nachkommastellen ein.');
            $net=parse_net_price($inputPrice);$cents=$net===null?null:vat_from_net($net)['gross_cents'];
            $features=array_values(array_filter(array_map('trim',explode("\n",text_field('features',3000)))));
            if (count($features)>10) throw new RuntimeException('Bitte verwende höchstens zehn Leistungsmerkmale.');
            $data=[$slug,$name,text_field('eyebrow',180),$category,['hosting'=>'globe','server'=>'server','domain'=>'compass','managed'=>'shield'][$category],text_field('description',2000),json_encode($features,JSON_UNESCAPED_UNICODE),$cents,$billing,isset($_POST['featured'])?1:0,isset($_POST['active'])?1:0];
            $id=(int)($_POST['id']??0);
            $exists=one('SELECT id FROM shop_products WHERE slug=? AND id<>?',[$slug,$id]);
            if($exists) throw new RuntimeException('Dieses URL-Kürzel ist bereits vergeben.');
            db()->beginTransaction();
            try{
                if($id) query('UPDATE shop_products SET slug=?,name=?,eyebrow=?,category=?,icon=?,description=?,features=?,price_cents=?,billing=?,featured=?,active=? WHERE id=?',[...$data,$id]);
                else{query('INSERT INTO shop_products (slug,name,eyebrow,category,icon,description,features,price_cents,billing,featured,active) VALUES (?,?,?,?,?,?,?,?,?,?,?)',$data);$id=(int)db()->lastInsertId();}
                save_product_options($id,$_POST,$net);
                save_product_contract_policy($id,$_POST,$billing);
                save_prepayment_options($id,$_POST);
                if($category==='domain' && isset($_POST['recommendations_present']))save_domain_recommendations($id,$_POST['recommendations']??[]);
                if(isset($_POST['package_editor'])){if(($_POST['sale_mode']??'direct')==='request')unset($_POST['direct_purchase']);save_package_configuration($id,$_POST);}
                db()->commit();
            }catch(Throwable $exception){db()->rollBack();throw $exception;}
            flash('Das Paket wurde gespeichert.'); redirect(isset($_POST['package_editor'])?'/admin/produkte?id='.$id:'/admin/produkte');
        case 'admin_service':
            require_admin();
            $customerId=(int)($_POST['customer_id']??0); $name=text_field('name',180); $identifier=text_field('identifier',253);$category=text_field('category',40);$status=text_field('status',25);
            if(!one("SELECT id FROM customers WHERE id=? AND status='active'",[$customerId]) || !$name || !$identifier) throw new RuntimeException('Bitte wähle einen Kunden und gib Produktname sowie Domain oder Kennung an.');
            if(!in_array($category,['hosting','server','domain','managed'],true) || !in_array($status,['active','pending','suspended'],true)) throw new RuntimeException('Ungültige Produkt-Einstellungen.');
            query('INSERT INTO shop_services (customer_id,name,category,identifier,status,details) VALUES (?,?,?,?,?,?)',[$customerId,$name,$category,$identifier,$status,text_field('details',5000)]);
            flash('Das bereitgestellte Produkt wurde dem Kunden zugeordnet.'); redirect('/admin/kunden?id='.$customerId);
        case 'admin_service_status':
            require_admin();$status=text_field('status',25);
            if(!in_array($status,['active','pending','suspended'],true)) throw new RuntimeException('Ungültiger Status.');
            query('UPDATE shop_services SET status=? WHERE id=?',[$status,(int)($_POST['id']??0)]);
            flash('Der Produktstatus wurde aktualisiert.'); redirect('/admin/kunden?id='.(int)($_POST['customer_id']??0));
        default: throw new RuntimeException('Diese Aktion ist nicht verfügbar.');
    }
}
