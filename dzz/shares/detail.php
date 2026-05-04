<?php
if (!defined('IN_OAOOA')) {//所有的php文件必须加上此句，防止被外部调用
    exit('Access Denied');
}
$operation = isset($_GET['operation']) ? trim($_GET['operation']) : '';
if($operation == 'fetch'){
    if(!$patharr=Pdecode($_GET['path'])){
        exit(json_encode(array('status'=>2,'error'=>lang('no_perm'))));
    }
    $rid = $patharr['path'];
    $isshare = $patharr['isshare'];
    $perm = $patharr['perm'];
    $isadmin = $patharr['isadmin'];
    $ulevel = getglobal('pichomelevel') ? getglobal('pichomelevel') : 0;
    if (!$rid) {
        exit(json_encode(array('status'=>2,'error'=>lang('no_perm'))));
    }

    $resourcesdata = C::t('pichome_resources')->fetch_by_rid($rid,$isshare,1,$perm);
    $appdata = C::t('pichome_vapp')->fetch($resourcesdata['appid']);
    if($perm){
        $resourcesdata['download'] =perm::check('download2',$perm)?1:0;
        $resourcesdata['share'] =perm::check('share',$perm)?1:0;
        $resourcesdata['view'] =perm::check('read2',$perm)?1:0;
        $resourcesdata['edit'] =perm::check('edit2',$perm)?1:0;
        $resourcesdata['collection'] =perm::check('collect',$perm)?1:0;
        //if($resourcesdata['edit']) $resourcesdata['dpath']=$_GET['path'];
        $resourcesdata['dpath']=Pencode(array('path' => $resourcesdata['rid'], 'perm' => $perm, 'ishare' => $isshare, 'isadmin' => $isadmin), 7200);
        if($resourcesdata['realfianllypath']){
            $resourcesdata['realfianllypath']=$_G['siteurl'].'index.php?mod=io&op=getStream&path='.$resourcesdata['dpath'];
        }
    }
    if((!isset($resourcesdata['view']) || !$resourcesdata['view']) && !$isshare && !C::t('pichome_vapp')->getpermbypermdata($appdata['view'],$resourcesdata['appid'])){
        exit(json_encode(array('status'=>2,'error'=>lang('no_perm'))));
    }
    $resourcesdata['preview']=array();
    if(!$resourcesdata['iniframe']){
        $resourcesdata['preview'] = C::t('thumb_preview')->fetchPreviewByRid($rid);
        $resourcesCover = ['spath'=>$resourcesdata['icondata'],'lpath'=>$resourcesdata['originalimg']];
        if($resourcesdata['preview']) array_unshift($resourcesdata['preview'],$resourcesCover);

    }

    $appdata = C::t('pichome_vapp')->fetch($resourcesdata['appid']);
    $data['fileds'] = unserialize($appdata['fileds']);
    //获取tab数据
    $tabstatus = 0;
    Hook::listen('checktab', $tabstatus);
    if($tabstatus){
        foreach($data['fileds'] as $v){
            if($v['type'] == 'tabgroup'){
                $gid =  intval(str_replace('tabgroup_','',$v['flag']));
                $tids = [];
                foreach(DB::fetch_all("select tid from %t where rid= %s and gid = %d",array('pichome_resourcestab',$rid,$gid)) as $val){
                    $tids[] = $val['tid'];
                }
                Hook::listen('gettab',$tids);
                $data[$v['flag']] = $tids;
            }
        }
    }
    $resourcesdata = array_merge($resourcesdata,$data);
    //增加浏览次数
    if($resourcesdata){
        addFileviewStats($rid,$isadmin);
    }
    Hook::listen('getDetailRighturl',$resourcesdata);
    if($isshare || $ulevel >= $resourcesdata['level']){
        exit(json_encode(array('status'=>1,'resourcesdata' => $resourcesdata,'sitename'=>$_G['setting']['sitename'])));
    }else{

        exit(json_encode(array('status'=>2,'error'=>lang('no_perm'))));
    }


}else {

    $_G['setting']['sitename'] = addslashes($_G['setting']['sitename']);
    $path=trim($_GET['path']);
    if (!$patharr = Pdecode($_GET['path'])) {
        showmessage('share not found or expired');
    }
    $referer=$_G['siteurl'].'index.php?mod=share&op=detail&path='.$path;
    $referer=urlencode($referer);
    $perm = $patharr['perm'];
    $sid = $patharr['sid'];
    $rid = $patharr['path'];

    $resourcesdata = C::t('pichome_resources')->fetch_by_rid($rid, 0, 0, $perm);

    $resourcesdata['share'] = 0;

    if (perm::check('download2', $perm)) {
        $resourcesdata['download'] = 1;
    } else {
        $resourcesdata['download'] = 0;
    }

//if(getglobal('adminid') != 1)$resourcesdata['download'] = 0;
    $colors = array();
    foreach ($resourcesdata['colors'] as $cval) {
        $colors[] = $cval;
    }
    $resourcesdata['colors'] = ($colors);

    $tag = array();
    foreach ($resourcesdata['tag'] as $tval) {
        if ($tval) {
            $tag[] = $tval;
        }
    }
    $resourcesdata['tag'] = ($tag);

//处理多预览图
    $previews = array();
    if (!$resourcesdata['iniframe']) {
        $previews = C::t('thumb_preview')->fetchPreviewByRid($rid, false, $perm);
        $resourcesCover = ['spath' => $resourcesdata['icondata'], 'lpath' => $resourcesdata['originalimg']];
        if (is_array($previews)) array_unshift($previews, $resourcesCover);
    }
    $resourcesdata['preview'] = ($previews);
    $previews = json_encode($previews);
    $foldernames = array();
    foreach ($resourcesdata['foldernames'] as $fval) {
        $foldernames[] = $fval;
    }
    $resourcesdata['foldernames'] = ($foldernames);
    $data_json = json_encode($resourcesdata);
    $theme = GetThemeColor();
    $ismobile = helper_browser::ismobile();
    if (($ismobile)) {
        include template('detail/mobile/page/details');
    } else {
        include template('detail/pc/page/index');
    }
}
