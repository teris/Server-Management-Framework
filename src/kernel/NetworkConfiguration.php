<?php
namespace SMF\Core;
final class NetworkConfiguration {
    public static function update(array $input,callable $transport): mixed {
        $node=$input['node']??'';$type=$input['type']??'qemu';$id=(string)($input['vmid']??'');$mac=strtoupper($input['mac']??'');$address=$input['ip']??'';
        if(!is_string($node)||!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/D',$node)||$type!=='qemu'||!ctype_digit($id)||(int)$id<100||!preg_match('/^(?:[0-9A-F]{2}:){5}[0-9A-F]{2}$/D',$mac)||(hexdec(substr($mac,0,2))&1))throw new \InvalidArgumentException('Node, QEMU-ID und gültige Unicast-MAC angeben.');
        $parts=explode('/',$address);$version=filter_var($parts[0]??'',FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)?4:(filter_var($parts[0]??'',FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)?6:0);
        if(!$version||count($parts)!==2||!ctype_digit($parts[1])||(int)$parts[1]>($version===4?32:128))throw new \InvalidArgumentException('IP-Adresse einschließlich CIDR-Präfix angeben.');
        $gateway=$input['gateway']??'';
        if($gateway!==''&&!filter_var($gateway,FILTER_VALIDATE_IP,$version===4?FILTER_FLAG_IPV4:FILTER_FLAG_IPV6))throw new \InvalidArgumentException('Gateway muss dieselbe IP-Version verwenden.');
        $path='/nodes/'.rawurlencode($node).'/qemu/'.(int)$id.'/config';
        $response=$transport('GET',$path,null);if(is_array($response)&&isset($response['success'])&&!$response['success'])throw new \RuntimeException('VM-Konfiguration nicht bestätigt.');
        $config=$response['data']??$response;
        if(!is_array($config)||!isset($config['net0'],$config['digest']))throw new \RuntimeException('VM-Netzwerk oder Konfigurationsstand fehlt.');
        $cloudInit=false;foreach($config as$key=>$value)if(preg_match('/^(?:ide|sata|scsi)\d+$/D',$key)&&is_string($value)&&str_contains($value,'cloudinit'))$cloudInit=true;
        if(!$cloudInit)throw new \RuntimeException('IP-Änderungen benötigen ein geprüftes Cloud-Init-Laufwerk.');
        if(!preg_match('/^(virtio|e1000|e1000e|rtl8139|vmxnet3|ne2k_pci|pcnet)=[0-9A-Fa-f:]{17}(,.*)?$/D',$config['net0'],$network))throw new \RuntimeException('Vorhandene Netzwerkkarte kann nicht sicher geändert werden.');
        $ipconfig=($version===4?'ip=':'ip6=').$address.($gateway!==''?($version===4?',gw=':',gw6=').$gateway:'');
        // Retain the other IP family, bridge, VLAN, firewall, queues and all existing NIC options.
        foreach(explode(',',$config['ipconfig0']??'')as$part)if($part!=='' && preg_match($version===4?'/^(?:ip6|gw6)=/':'/^(?:ip|gw)=/',$part))$ipconfig.=','.$part;
        $result=$transport('PUT',$path,['net0'=>$network[1].'='.$mac.($network[2]??''),'ipconfig0'=>$ipconfig,'digest'=>$config['digest']]);
        if($result===false || (is_array($result)&&isset($result['success'])&&!$result['success']))throw new \RuntimeException('Netzwerkänderung nicht bestätigt; Anbieterzustand prüfen.');
        return $result;
    }
}
