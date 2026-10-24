<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function checkApplicationQueries(PDO $db): int {
    $root=dirname(__DIR__);$files=[$root.'/framework.php'];
    foreach(['src','public']as$area){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$area,FilesystemIterator::SKIP_DOTS));foreach($it as$file)if($file->getExtension()==='php'&&!str_contains($file->getPathname(),'/debug/')&&!in_array($file->getFilename(),['debug.php','auth.php','info.php','setup.php','install.php'],true))$files[]=$file->getPathname();}
    $seen=[];$failures=[];
    // The named legacy-module dialect is exercised against an actual restored snapshot.
    foreach($files as$file){if(basename($file)==='LegacyModuleSchema.php')continue;foreach(token_get_all(file_get_contents($file))as$token){
        if(!is_array($token)||$token[0]!==T_CONSTANT_ENCAPSED_STRING)continue;
        $literal=substr($token[1],1,-1);$sql=$token[1][0]==="'"?str_replace(["\\'","\\\\"],["'","\\"],$literal):stripcslashes($literal);$sql=trim($sql);
        if(!preg_match('/^(SELECT|UPDATE|INSERT INTO|DELETE FROM)\b/i',$sql)||str_contains($sql,'$')||!preg_match('/\b(?:FROM|INTO|UPDATE|JOIN)\s+[`"]?[a-zA-Z_][a-zA-Z0-9_]*/i',$sql))continue;
        if(preg_match('/sqlite_master|information_schema|pg_tables|pg_views|pg_class|pg_trigger|pg_type|pg_constraint|to_regclass|sqlite_sequence/i',$sql))continue;
        if(preg_match('/\b(?:FROM|INTO|UPDATE|JOIN)\s*$/i',$sql))continue;
        if(preg_match('/^UPDATE\b/i',$sql)&&!preg_match('/^UPDATE\s+\S+\s+SET\s+\S/i',$sql))continue;
        if(preg_match('/^INSERT INTO\b/i',$sql)&&!preg_match('/\b(?:VALUES|SELECT)\b/i',$sql))continue;
        if(substr_count($sql,'(')!==substr_count($sql,')')||preg_match('/(?:\bSET|\bWHERE|\bAND|\bOR|\bIN|=|,)\s*$/i',$sql))continue;
        // Optional shop SQL is checked only with installed shop tables.
        if(preg_match_all('/\b(?:FROM|INTO|UPDATE|JOIN)\s+(shop_[a-z0-9_]+)/i',$sql,$optional)){foreach($optional[1]as$table)if(!SchemaManager::hasTable($db,$table))continue 2;}
        $key=hash('sha256',$sql);if(isset($seen[$key]))continue;$seen[$key]=true;
        try{$stmt=$db->prepare('EXPLAIN '.$sql);for($i=1;$i<=substr_count($sql,'?');$i++)$stmt->bindValue($i,null,PDO::PARAM_NULL);$stmt->execute();$stmt->fetchAll();$stmt->closeCursor();}
        catch(Throwable$e){$failures[]=str_replace($root.'/','',$file).':'.$token[2].' '.$e->getMessage();}
    }}
    if($failures)throw new RuntimeException("Application SQL/schema mismatches:\n".implode("\n",$failures));
    return count($seen);
}
