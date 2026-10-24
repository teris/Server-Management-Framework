<?php
/** Customer presentation only. Billing, provider access and termination jobs keep their records. */
function customer_order_visibility_sql(): string {
    return "o.fulfillment_status NOT IN ('cancelled','ended') AND o.payment_status<>'cancelled'
        AND COALESCE(c.state,'') NOT IN ('cancel_requested','ended','unpaid_cancelled') AND COALESCE(c.cancel_at,0)=0
        AND NOT EXISTS (SELECT 1 FROM shop_module_intents mi LEFT JOIN shop_orders parent ON parent.id=mi.parent_order_id LEFT JOIN shop_contracts pc ON pc.order_id=parent.id
            WHERE mi.order_id=o.id AND (parent.id IS NULL OR parent.customer_id<>o.customer_id OR parent.fulfillment_status IN ('cancelled','ended') OR parent.payment_status='cancelled' OR COALESCE(pc.state,'') IN ('cancel_requested','ended','unpaid_cancelled') OR COALESCE(pc.cancel_at,0)>0))";
}
function customer_orders(int $customerId,?int $orderId=null): array {
    $sql='SELECT o.*,c.state AS contract_state,c.policy,c.started_at,c.paid_until,c.cancel_at FROM shop_orders o LEFT JOIN shop_contracts c ON c.order_id=o.id WHERE o.customer_id=? AND '.customer_order_visibility_sql();$args=[$customerId];
    if($orderId!==null){$sql.=' AND o.id=?';$args[]=$orderId;}
    return all($sql.' ORDER BY o.id DESC',$args);
}
function customer_order(int $orderId,int $customerId): ?array {return customer_orders($customerId,$orderId)[0]??null;}
function customer_services(int $customerId,bool $serversOnly=false): array {
    $hidden=array_fill_keys(array_column(all('SELECT o.id FROM shop_orders o LEFT JOIN shop_contracts c ON c.order_id=o.id WHERE o.customer_id=? AND NOT ('.customer_order_visibility_sql().')',[$customerId]),'id'),true);
    $rows=all("SELECT s.*,l.provider AS linked_provider,l.metadata AS visibility_metadata FROM shop_services s LEFT JOIN shop_resource_links l ON l.service_id=s.id WHERE s.customer_id=? AND s.status NOT IN ('cancelled','ended')".($serversOnly?" AND s.category='server'":'').' ORDER BY s.id DESC',[$customerId]);
    return array_values(array_filter(array_map(function($service)use($hidden){$orderId=(int)(json_decode($service['visibility_metadata']??'{}',true)['order_id']??0);unset($service['visibility_metadata']);return isset($hidden[$orderId])?null:$service;},$rows)));
}
function customer_service_visible(int $serviceId,int $customerId): bool {
    foreach(customer_services($customerId) as $service)if((int)$service['id']===$serviceId)return true;
    return false;
}
function customer_module_intents(int $parentId,int $customerId): array {
    return array_values(array_filter(all("SELECT * FROM shop_module_intents WHERE parent_order_id=? AND customer_id=? AND state NOT IN ('ended','cancel_requested') ORDER BY id",[$parentId,$customerId]),fn($intent)=>!$intent['order_id'] || customer_order((int)$intent['order_id'],$customerId)!==null));
}
