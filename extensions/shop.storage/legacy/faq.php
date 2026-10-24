<?php
function faq_categories(): array {return ['email'=>'E-Mail einrichten','ftp'=>'FTP & Dateien','ssh'=>'SSH & VPS','general'=>'Allgemeine Fragen'];}
function faq_save(int $id,array $input): int {
    $slug=trim((string)($input['slug']??''));$title=trim((string)($input['title']??''));$body=trim((string)($input['body']??''));$category=(string)($input['category']??'general');if(!preg_match('/^[a-z0-9-]{2,100}$/D',$slug) || !$title || strlen($title)>200 || !$body || strlen($body)>20000 || !isset(faq_categories()[$category]))throw new RuntimeException('Bitte Titel, URL-Kürzel, Bereich und Anleitung prüfen.');
    $links=[];foreach(preg_split('/\R/',trim((string)($input['links']??''))) as $line){if(trim($line)==='')continue;[$caption,$url]=array_pad(explode('|',$line,2),2,'');$caption=trim($caption);$url=trim($url);if(!$caption || strlen($caption)>200)throw new RuntimeException('Download-Links bitte als Bezeichnung | https://… eintragen.');$links[]=['label'=>$caption,'url'=>checked_document_url($url)];if(count($links)>20)throw new RuntimeException('Bitte höchstens 20 Links verwenden.');}
    $args=[$slug,$title,$category,$body,json_encode($links),isset($input['published'])?1:0,max(0,min(9999,(int)($input['sort_order']??0)))];
    if(one('SELECT id FROM shop_faq WHERE slug=? AND id<>?',[$slug,$id]))throw new RuntimeException('Dieses FAQ-Kürzel ist bereits vergeben.');
    if($id){if(!one('SELECT id FROM shop_faq WHERE id=?',[$id]))throw new RuntimeException('FAQ-Eintrag nicht gefunden.');query('UPDATE shop_faq SET slug=?,title=?,category=?,body=?,links=?,published=?,sort_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[...$args,$id]);}
    else{query('INSERT INTO shop_faq (slug,title,category,body,links,published,sort_order) VALUES (?,?,?,?,?,?,?)',$args);$id=(int)db()->lastInsertId();}audit_event('faq.save',(string)$id);return $id;
}
function published_faqs(string $category='',string $search=''): array {
    $sql='SELECT * FROM shop_faq WHERE published=1';$args=[];if(isset(faq_categories()[$category])){$sql.=' AND category=?';$args[]=$category;}if($search!==''){$sql.=' AND (title LIKE ? OR body LIKE ?)';$args[]='%'.$search.'%';$args[]='%'.$search.'%';}return all($sql.' ORDER BY sort_order,id',$args);
}
