<?php
return static function(array $payload,SMF\Core\JobContext $context):void {
    $service=(new SMF\Core\ProductRegistry(SMF\Core\Kernel::boot()->modules()))->service('gameserver');
    if(($payload['operation']??'tick')==='control')$service->control($payload,$context);
    else {$context->checkpoint('running',[]);$service->tick();}
};
