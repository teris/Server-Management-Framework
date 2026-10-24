<?php
function document_categories(): array {return ['terms'=>'AGB / Vertragsbedingungen','withdrawal'=>'Widerrufsbelehrung','privacy'=>'Datenschutz','other'=>'Weiteres Dokument'];}
function document_directory(): string {$dir=shop_private().'/documents';if(!is_dir($dir) && !mkdir($dir,0700,true))throw new RuntimeException('Der Dokumentenspeicher ist nicht erreichbar.');return $dir;}
function document_upload(array $upload): string {
    if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || (int)($upload['size']??0)<10 || (int)$upload['size']>8*1024*1024 || strtolower(pathinfo((string)($upload['name']??''),PATHINFO_EXTENSION))!=='pdf')throw new RuntimeException('Bitte eine PDF-Datei mit höchstens 8 MB auswählen.');
    $path=(string)$upload['tmp_name'];if(!is_uploaded_file($path) && !(PHP_SAPI==='cli' && ORGA_LOCAL))throw new RuntimeException('Die PDF konnte nicht hochgeladen werden.');$bytes=file_get_contents($path);if(!str_starts_with($bytes,'%PDF-') || !str_contains(substr($bytes,-2048),'%%EOF'))throw new RuntimeException('Die Datei ist keine vollständige PDF.');
    $name=bin2hex(random_bytes(24)).'.pdf';if(file_put_contents(document_directory().'/'.$name,$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('Die PDF konnte nicht gespeichert werden.');chmod(document_directory().'/'.$name,0600);return $name;
}
function save_shop_document(int $id,array $input,?array $upload=null): int {
    $old=$id?one('SELECT * FROM shop_documents WHERE id=?',[$id]):null;if($id && !$old)throw new RuntimeException('Dokument nicht gefunden.');
    $slug=trim((string)($input['slug']??''));$title=trim((string)($input['title']??''));$category=(string)($input['category']??'other');$content=trim((string)($input['content']??''));
    if(!preg_match('/^[a-z0-9-]{2,80}$/D',$slug) || !$title || mb_strlen($title)>180 || !isset(document_categories()[$category]) || strlen($content)>60000 || str_contains($content,"\0"))throw new RuntimeException('Bitte Titel, URL-Kürzel, Dokumenttyp und Text prüfen (maximal 60.000 Zeichen).');
    if($old && $old['published_version'] && $slug!==$old['slug'])throw new RuntimeException('Das URL-Kürzel eines veröffentlichten Dokuments bleibt erhalten.');if(one('SELECT id FROM shop_documents WHERE slug=? AND id<>?',[$slug,$id]))throw new RuntimeException('Dieses Dokument-Kürzel ist bereits vergeben.');
    $file=$old['draft_file']??null;if(isset($input['use_text']))$file=null;if($upload && ($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$file=document_upload($upload);
    if($id)query('UPDATE shop_documents SET title=?,slug=?,category=?,draft_content=?,draft_file=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$title,$slug,$category,$content,$file,$id]);
    else{query('INSERT INTO shop_documents (title,slug,category,draft_content,draft_file) VALUES (?,?,?,?,?)',[$title,$slug,$category,$content,$file]);$id=(int)db()->lastInsertId();}audit_event('document.draft.saved',(string)$id);return $id;
}
function document_version_url(array $version,bool $pdf=true): string {$d=one('SELECT slug FROM shop_documents WHERE id=?',[$version['document_id']]);return Config::FRONTPANEL_SITE_URL.'/dokumente?dokument='.rawurlencode($d['slug']).'&version='.$version['id'].($pdf?'&pdf=1':'');}
function publish_shop_document(int $id,int $administrator,bool $checkout): array {
    db()->beginTransaction();try{
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$d=one('SELECT * FROM shop_documents WHERE id=?'.$lock,[$id]);if(!$d || (!$d['draft_file'] && mb_strlen($d['draft_content'])<20))throw new RuntimeException('Bitte zuerst deinen Dokumenttext oder eine PDF speichern.');
        $number=(int)query('SELECT COALESCE(MAX(version_no),0)+1 FROM shop_document_versions WHERE document_id=?',[$id])->fetchColumn();query('INSERT INTO shop_document_versions (document_id,version_no,title,content,source_file,created_by) VALUES (?,?,?,?,?,?)',[$id,$number,$d['title'],$d['draft_content'],$d['draft_file'],$administrator]);$v=one('SELECT * FROM shop_document_versions WHERE id=?',[db()->lastInsertId()]);shop_document_pdf($v);query('UPDATE shop_documents SET published_version=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$v['id'],$id]);
        if($checkout){if(in_array($d['category'],['terms','withdrawal'],true)){$documents=checkout_documents();$documents[$d['category']==='terms'?'terms_url':'withdrawal_url']=document_version_url($v);save_package_documents($documents);}else shop_meta_set('checkout_document_'.$d['category'],(string)$v['id']);}db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}audit_event('document.published',(string)$v['id']);return $v;
}
function shop_document_pdf(array $version): string {
    $dir=document_directory();if($version['pdf_file']){$file=$dir.'/'.basename($version['pdf_file']);if(is_file($file) && hash_equals($version['pdf_hash']??'',hash_file('sha256',$file)))return $file;throw new RuntimeException('Die gespeicherte Dokumentfassung muss geprüft werden.');}
    if($version['source_file']){$bytes=file_get_contents($dir.'/'.basename($version['source_file']));}
    else{$pdf=new ShopInvoicePdf();$pdf->AddPage();$pdf->SetFont('DejaVu','B',18);$pdf->MultiCell(0,9,$version['title']);$pdf->Ln(3);$pdf->SetFont('DejaVu','',9);$pdf->MultiCell(0,5,'Fassung '.$version['version_no'].' · '.date_de($version['created_at']));$pdf->Ln(6);$pdf->SetFont('DejaVu','',10);$pdf->MultiCell(0,5.5,$version['content']);$bytes=$pdf->Output('S');}
    $name='version-'.$version['id'].'-'.bin2hex(random_bytes(16)).'.pdf';$file=$dir.'/'.$name;if(file_put_contents($file,$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('Die Dokument-PDF konnte nicht gespeichert werden.');chmod($file,0600);
    $changed=query('UPDATE shop_document_versions SET pdf_file=?,pdf_hash=? WHERE id=? AND pdf_file IS NULL',[$name,hash('sha256',$bytes),$version['id']]);if($changed->rowCount()!==1){unlink($file);return shop_document_pdf(one('SELECT * FROM shop_document_versions WHERE id=?',[$version['id']]));}return $file;
}
function checkout_document_versions(array $urls): array {
    $versions=[];foreach($urls as $url){$parts=parse_url((string)$url);$query=[];parse_str($parts['query']??'',$query);if(($parts['host']??'')!==parse_url(Config::FRONTPANEL_SITE_URL,PHP_URL_HOST) || ($parts['path']??'')!=='/dokumente')continue;$v=one('SELECT v.id FROM shop_document_versions v JOIN shop_documents d ON d.id=v.document_id WHERE v.id=? AND d.slug=?',[(int)($query['version']??0),(string)($query['dokument']??'')]);if($v)$versions[]=(int)$v['id'];}return array_values(array_unique($versions));
}
function public_document_download(): void {
    $d=one('SELECT * FROM shop_documents WHERE slug=?',[(string)($_GET['dokument']??'')]);$v=$d?one('SELECT * FROM shop_document_versions WHERE id=? AND document_id=?',[(int)($_GET['version']??$d['published_version']),$d['id']]):null;
    if(!$v){http_response_code(404);return;}if(!isset($_GET['pdf']))return;$file=shop_document_pdf($v);while(ob_get_level())ob_end_clean();header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="'.$d['slug'].'-v'.$v['version_no'].'.pdf"');header('X-Content-Type-Options: nosniff');header('Cache-Control: public, max-age=3600');header('Content-Length: '.filesize($file));readfile($file);exit;
}
function extra_checkout_documents(): array {
    $rows=[];foreach(['privacy','other'] as $kind){$id=(int)shop_meta_get('checkout_document_'.$kind,'0');$v=$id?one('SELECT * FROM shop_document_versions WHERE id=?',[$id]):null;if($v)$rows[]=$v;}return $rows;
}
function document_versions_for_product(array $p): array {return $p['_document_versions']??array_values(array_unique(array_merge(checkout_document_versions(checkout_documents_for($p)),array_column(extra_checkout_documents(),'id'))));}

function checkout_extra_versions_for_product(array $p): array {if(!isset($p['_document_versions']))return extra_checkout_documents();$rows=[];foreach($p['_document_versions'] as $id){$v=one("SELECT v.* FROM shop_document_versions v JOIN shop_documents d ON d.id=v.document_id WHERE v.id=? AND d.category IN ('privacy','other')",[(int)$id]);if($v)$rows[]=$v;}return $rows;}
