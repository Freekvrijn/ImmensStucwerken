<?php
require_once __DIR__.'/../public_html/_lib/offertes.php';
$base=offerte_config(); $fixtures=array();
function fixture($name,$config,$input,$subtotal,$vat,$total) {
    global $fixtures;
    $result=offerte_bereken($config,$input);
    if($result['subtotaal_cent']!==$subtotal || $result['btw_cent']!==$vat || $result['totaal_cent']!==$total) { throw new RuntimeException('Onjuiste berekening: '.$name); }
    $fixtures[]=array('name'=>$name,'config'=>$config,'input'=>$input,'result'=>$result);
}
fixture('Factuurhoeveelheden, 21%',$base,array('aantallen'=>array('wanden'=>'44','plafonds'=>'16'),'pand'=>'onbekend'),165000,34650,199650);
fixture('Oudere woning, 9%',$base,array('aantallen'=>array('wanden'=>'44','plafonds'=>'16'),'pand'=>'woning-oud'),165000,14850,179850);
$config=$base;$config['diensten'][0]['prijs_cent']=2501;$config['reiskosten_cent']=0;
fixture('Halve cent per regel rondt omhoog',$config,array('aantallen'=>array('wanden'=>'0.5'),'pand'=>'overig'),1251,263,1514);
fixture('Komma als decimaalteken',$config,array('aantallen'=>array('wanden'=>'0,5'),'pand'=>'overig'),1251,263,1514);
$config=$base;$config['minimum_cent']=50000;
fixture('Minimum geldt voor werk, voor reiskosten',$config,array('aantallen'=>array('wanden'=>'1'),'pand'=>'overig'),65000,13650,78650);
$config=$base;$config['woning_btw']=false;$config['btw_standaard']=21;
fixture('Vast btw-tarief',$config,array('aantallen'=>array('wanden'=>'1'),'pand'=>'woning-oud'),17500,3675,21175);
$config=$base;$config['diensten'][0]['prijs_cent']=0;$config['reiskosten_cent']=0;
fixture('Gratis dienst',$config,array('aantallen'=>array('wanden'=>'1'),'pand'=>'overig'),0,0,0);
$config=$base;$config['diensten'][]=array('id'=>'herstel','naam'=>'Herstelwerk','eenheid'=>'uur','prijs_cent'=>5500,'actief'=>true);
fixture('Extra beheerde dienst',$config,array('aantallen'=>array('herstel'=>'2.5'),'pand'=>'overig'),28750,6038,34788);
foreach(array('-1','NaN','INF','1e3','0.001','10000.01','2,0.1',array('1')) as $value) {
    try { offerte_decimal($value); throw new RuntimeException('Ongeldig getal geaccepteerd'); } catch(InvalidArgumentException $e) {}
}
$config=$base;$config['diensten'][0]['actief']=false;
try { offerte_bereken($config,array('aantallen'=>array('wanden'=>'1'),'pand'=>'overig')); throw new RuntimeException('Verborgen dienst geaccepteerd'); } catch(InvalidArgumentException $e) {}
$config=$base;$config['actief']=false;
try { offerte_bereken($config,array('aantallen'=>array('wanden'=>'1'),'pand'=>'overig')); throw new RuntimeException('Uitgeschakelde calculator geaccepteerd'); } catch(InvalidArgumentException $e) {}
if(($argv[1]??'')==='--fixtures') { echo json_encode($fixtures,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
else { echo count($fixtures)." exacte prijsberekeningen en tien afwijzingen gecontroleerd.\n"; }
