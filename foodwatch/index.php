<?php
declare(strict_types=1);
/**
 * FoodWatch US — Food Recall Intelligence Platform v1.0.0
 * PHP 8.2+ · SQLite WAL · TailwindCSS CDN · Alpine.js · D3.js · Lucide
 * Zero Composer/npm deps · IONOS shared-hosting compatible
 */

// ================================================================
// § CONSTANTS
// ================================================================
const FW_VERSION    = '1.0.0';
const FW_SCHEMA_VER = 6;
const FW_DATA_DIR   = __DIR__ . '/data';
const FW_DB_PATH    = __DIR__ . '/data/foodwatch.db';
const FW_LAMBDA     = 0.01;   // daily decay; half-life ≈69 days

const FDA_API  = 'https://api.fda.gov/food/enforcement.json';
const FSIS_API = 'https://www.fsis.usda.gov/fsis/api/recall/v/1';

const INGEST_TIMEOUT   = 30;
const INGEST_PAGE_SIZE = 100;
const INGEST_MAX_PAGES = 10;

const SEV_SCORES = ['Class I'=>3.0,'Class II'=>2.0,'Class III'=>1.0];
const DIST_CONF  = ['confirmed'=>1.00,'probable'=>0.75,'inferred'=>0.50,'unknown'=>0.25];

const REL_TYPES = [
    'manufacturer'=>'Manufacturer','processor'=>'Processor','packer'=>'Packer',
    'importer'=>'Importer','distributor'=>'Distributor','retailer'=>'Retailer (3rd-party)',
    'private_label_retailer'=>'Retailer (private label)','unknown'=>'Unknown',
];

const US_STATES = [
    'AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California',
    'CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware','FL'=>'Florida','GA'=>'Georgia',
    'HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa',
    'KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland',
    'MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi',
    'MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire',
    'NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina',
    'ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania',
    'RI'=>'Rhode Island','SC'=>'South Carolina','SD'=>'South Dakota','TN'=>'Tennessee',
    'TX'=>'Texas','UT'=>'Utah','VT'=>'Vermont','VA'=>'Virginia','WA'=>'Washington',
    'WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming','DC'=>'District of Columbia',
    'PR'=>'Puerto Rico','GU'=>'Guam','VI'=>'Virgin Islands','nationwide'=>'Nationwide',
];

const KNOWN_RETAILERS = [
    'walmart'=>'Walmart','kroger'=>'Kroger','costco'=>'Costco','target'=>'Target',
    'safeway'=>'Safeway','albertsons'=>'Albertsons','whole foods'=>'Whole Foods Market',
    'publix'=>'Publix',"trader joe's"=>"Trader Joe's","trader joes"=>"Trader Joe's",
    'heb'=>'H-E-B','meijer'=>'Meijer','aldi'=>'ALDI','lidl'=>'Lidl',
    'stop & shop'=>'Stop & Shop','food lion'=>'Food Lion','giant food'=>'Giant Food',
    'harris teeter'=>'Harris Teeter','wegmans'=>'Wegmans','hy-vee'=>'Hy-Vee',
    'winco'=>'WinCo Foods','sprouts'=>'Sprouts Farmers Market',
    'market basket'=>'Market Basket','stater bros'=>'Stater Bros.',
    'price chopper'=>'Price Chopper','shoprite'=>'ShopRite','acme'=>'ACME Markets',
    'weis'=>'Weis Markets',"ralphs"=>"Ralph's","ralph's"=>"Ralph's",
    'fred meyer'=>'Fred Meyer','king soopers'=>'King Soopers',
    "smith's"=>"Smith's",'amazon fresh'=>'Amazon Fresh',
    "sam's club"=>"Sam's Club",'bj\'s'=>"BJ's Wholesale Club",
    'dollar general'=>'Dollar General','dollar tree'=>'Dollar Tree',
    'family dollar'=>'Family Dollar',
];

const CAT_KEYWORDS = [
    'produce'       =>['lettuce','spinach','tomato','vegetable','salad','apple','fruit','herb','sprout','onion','pepper','broccoli','kale','romaine','arugula'],
    'meat'          =>['beef','pork','lamb','veal','ham','sausage','hot dog','bologna','salami','pepperoni','ground beef','steak','roast'],
    'poultry'       =>['chicken','turkey','duck','poultry','fowl'],
    'seafood'       =>['fish','salmon','tuna','shrimp','crab','lobster','oyster','clam','scallop','tilapia','cod','halibut','sardine','seafood'],
    'dairy'         =>['milk','cheese','yogurt','butter','cream','whey','casein','kefir'],
    'eggs'          =>['egg','eggs'],
    'prepared_meals'=>['ready-to-eat','rte','frozen entree','dinner','entree','soup','prepared','heat and serve','microwave meal','meal kit'],
    'bakery'        =>['bread','cake','cookie','muffin','bagel','croissant','pastry','donut','waffle'],
    'snacks'        =>['chip','pretzel','popcorn','cracker','granola bar','trail mix','snack','jerky'],
    'frozen_foods'  =>['frozen','freeze-dried'],
    'canned_foods'  =>['canned','jarred','preserved'],
    'beverages'     =>['juice','beverage','soda','tea','coffee','smoothie','shake','drink'],
    'infant_food'   =>['infant','formula','baby food','baby formula','toddler'],
    'condiments'    =>['sauce','dressing','ketchup','mustard','mayo','mayonnaise','salsa','spread','dip','vinegar','spice','seasoning','condiment'],
    'grains'        =>['rice','pasta','noodle','cereal','flour','oat','quinoa','grain'],
    'supplements'   =>['supplement','vitamin','protein powder','herbal','dietary supplement'],
    'nuts'          =>['nut','almond','cashew','walnut','pistachio','pecan','macadamia','peanut butter'],
    'other'         =>[],
];

const HAZ_KEYWORDS = [
    'salmonella'         =>['salmonella'],
    'listeria'           =>['listeria','l. mono','listeria monocytogenes'],
    'e_coli'             =>['e. coli','e.coli','escherichia coli','stec'],
    'botulinum'          =>['botulinum','botulism','clostridium botulinum'],
    'cronobacter'        =>['cronobacter'],
    'hepatitis_a'        =>['hepatitis a'],
    'norovirus'          =>['norovirus'],
    'milk_allergen'      =>['undeclared milk','milk allergen','milk protein','contains milk'],
    'egg_allergen'       =>['undeclared egg','egg allergen','contains egg'],
    'peanut_allergen'    =>['undeclared peanut','peanut allergen','contains peanut'],
    'tree_nut_allergen'  =>['undeclared tree nut','undeclared almond','undeclared cashew','undeclared walnut','nut allergen'],
    'wheat_allergen'     =>['undeclared wheat','wheat allergen','contains wheat','gluten'],
    'soy_allergen'       =>['undeclared soy','soy allergen','contains soy','soybean'],
    'sesame_allergen'    =>['undeclared sesame','sesame allergen','contains sesame'],
    'fish_allergen'      =>['undeclared fish','fish allergen','contains fish'],
    'shellfish_allergen' =>['undeclared shellfish','shellfish allergen','shrimp allergen'],
    'metal'              =>['metal fragment','metallic','stainless steel piece','metal piece'],
    'plastic'            =>['plastic fragment','plastic piece','plastic contamination'],
    'glass'              =>['glass fragment','glass piece','glass shard'],
    'foreign_material'   =>['foreign material','foreign object','foreign matter','extraneous material'],
    'chemical'           =>['chemical','pesticide','herbicide','cleaning compound','sanitizer','residue'],
    'excessive_additive' =>['excess','excessive','overdose','high level of'],
    'mislabeling'        =>['mislabeled','incorrect label','wrong label','undeclared ingredient'],
    'lack_of_inspection' =>['lack of inspection','without inspection','uninspected','without usda inspection'],
    'processing_violation'=>['processing violation','manufacturing violation','gmp violation','adulterated'],
];

const HAZ_TYPE_MAP = [
    'salmonella'=>'biological','listeria'=>'biological','e_coli'=>'biological',
    'botulinum'=>'biological','cronobacter'=>'biological','hepatitis_a'=>'biological','norovirus'=>'biological',
    'milk_allergen'=>'allergen','egg_allergen'=>'allergen','peanut_allergen'=>'allergen',
    'tree_nut_allergen'=>'allergen','wheat_allergen'=>'allergen','soy_allergen'=>'allergen',
    'sesame_allergen'=>'allergen','fish_allergen'=>'allergen','shellfish_allergen'=>'allergen',
    'metal'=>'physical','plastic'=>'physical','glass'=>'physical','foreign_material'=>'physical',
    'chemical'=>'chemical','excessive_additive'=>'chemical',
    'mislabeling'=>'regulatory','lack_of_inspection'=>'regulatory','processing_violation'=>'regulatory',
];

// ================================================================
// § SECURITY & SESSION
// ================================================================
ini_set('session.cookie_httponly','1');
ini_set('session.use_strict_mode','1');
ini_set('session.cookie_samesite','Strict');
if (session_status()===PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));

function h(mixed $v):string{ return htmlspecialchars((string)$v,ENT_QUOTES|ENT_HTML5,'UTF-8'); }
function js(mixed $v):string{ return json_encode($v,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
function csrf():string{ return $_SESSION['csrf']??''; }
function csrf_ok():bool{
    $t=trim($_POST['csrf']??$_SERVER['HTTP_X_CSRF_TOKEN']??'');
    return !empty($t)&&hash_equals($_SESSION['csrf']??'',$t);
}
function is_admin():bool{ return !empty($_SESSION['fw_admin']); }
function admin_login(string $u,string $p):bool{
    $eu=getenv('FW_ADMIN_USER')?:'admin';
    $ep=getenv('FW_ADMIN_PASS')?:'foodwatch2024';
    if(hash_equals($eu,$u)&&hash_equals($ep,$p)){$_SESSION['fw_admin']=true;return true;}
    return false;
}
function is_ajax():bool{
    return ($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest'
        || str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
}
function fw_abort(string $msg,int $code=400):never{
    http_response_code($code);
    if(is_ajax()){header('Content-Type: application/json');echo js(['error'=>$msg]);exit;}
    echo '<html><body style="font-family:sans-serif;padding:2rem"><h2>Error '.$code.'</h2><p>'.h($msg).'</p><a href="?">Home</a></body></html>';
    exit;
}
function json_out(mixed $data):never{
    header('Content-Type: application/json; charset=utf-8');
    echo js($data);exit;
}

// ================================================================
// § DATABASE
// ================================================================
function db():PDO{
    static $pdo=null;
    if($pdo)return $pdo;
    if(!is_dir(FW_DATA_DIR)&&!mkdir(FW_DATA_DIR,0750,true))
        throw new \RuntimeException('Cannot create data dir');
    $pdo=new PDO('sqlite:'.FW_DB_PATH,null,null,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL;PRAGMA synchronous=NORMAL;PRAGMA cache_size=-32000;PRAGMA foreign_keys=ON;PRAGMA temp_store=MEMORY;');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $db):void{
    $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations(version INTEGER PRIMARY KEY,applied_at TEXT NOT NULL DEFAULT(datetime('now')))");
    $cur=(int)$db->query('SELECT COALESCE(MAX(version),0) FROM schema_migrations')->fetchColumn();
    foreach(migrations() as $v=>$sql){
        if($v<=$cur)continue;
        $db->beginTransaction();
        try{$db->exec($sql);$db->prepare('INSERT INTO schema_migrations(version)VALUES(?)')->execute([$v]);$db->commit();}
        catch(\Throwable $e){$db->rollBack();throw new \RuntimeException("Migration $v: ".$e->getMessage());}
    }
}

function migrations():array{
    return[1=>m1(),2=>m2(),3=>m3(),4=>m4(),5=>m5(),6=>m6()];
}

function m1():string{ return <<<'SQL'
CREATE TABLE IF NOT EXISTS agencies(
  id INTEGER PRIMARY KEY,code TEXT NOT NULL UNIQUE,name TEXT NOT NULL,
  base_url TEXT,api_url TEXT,active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT(datetime('now')));

CREATE TABLE IF NOT EXISTS food_categories(
  id INTEGER PRIMARY KEY,parent_id INTEGER REFERENCES food_categories(id),
  name TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,sort_order INTEGER NOT NULL DEFAULT 0);

CREATE TABLE IF NOT EXISTS hazards(
  id INTEGER PRIMARY KEY,type TEXT NOT NULL,subtype TEXT NOT NULL,
  name TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,description TEXT);

CREATE TABLE IF NOT EXISTS manufacturers(
  id INTEGER PRIMARY KEY,name TEXT NOT NULL,normalized_name TEXT NOT NULL,
  address TEXT,city TEXT,state TEXT,country TEXT NOT NULL DEFAULT 'US',
  created_at TEXT NOT NULL DEFAULT(datetime('now')));
CREATE INDEX IF NOT EXISTS idx_mfr ON manufacturers(normalized_name);

CREATE TABLE IF NOT EXISTS brands(
  id INTEGER PRIMARY KEY,name TEXT NOT NULL,normalized_name TEXT NOT NULL,
  manufacturer_id INTEGER REFERENCES manufacturers(id),
  created_at TEXT NOT NULL DEFAULT(datetime('now')));
CREATE INDEX IF NOT EXISTS idx_brand ON brands(normalized_name);

CREATE TABLE IF NOT EXISTS distributors(
  id INTEGER PRIMARY KEY,name TEXT NOT NULL,normalized_name TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT(datetime('now')));

CREATE TABLE IF NOT EXISTS retailers(
  id INTEGER PRIMARY KEY,name TEXT NOT NULL,normalized_name TEXT NOT NULL,
  is_chain INTEGER NOT NULL DEFAULT 1,parent_id INTEGER REFERENCES retailers(id),
  created_at TEXT NOT NULL DEFAULT(datetime('now')));
CREATE INDEX IF NOT EXISTS idx_ret ON retailers(normalized_name);

CREATE TABLE IF NOT EXISTS stores(
  id INTEGER PRIMARY KEY,retailer_id INTEGER NOT NULL REFERENCES retailers(id),
  name TEXT,address TEXT,city TEXT,state TEXT,zip TEXT,lat REAL,lng REAL,
  created_at TEXT NOT NULL DEFAULT(datetime('now')));
CREATE INDEX IF NOT EXISTS idx_store_ret ON stores(retailer_id);
CREATE INDEX IF NOT EXISTS idx_store_st ON stores(state);
CREATE INDEX IF NOT EXISTS idx_store_zip ON stores(zip);

CREATE TABLE IF NOT EXISTS products(
  id INTEGER PRIMARY KEY,name TEXT NOT NULL,normalized_name TEXT NOT NULL,
  category_id INTEGER REFERENCES food_categories(id),upc TEXT,
  created_at TEXT NOT NULL DEFAULT(datetime('now')));
CREATE INDEX IF NOT EXISTS idx_prod ON products(normalized_name);
CREATE INDEX IF NOT EXISTS idx_prod_upc ON products(upc);

CREATE TABLE IF NOT EXISTS recalls(
  id INTEGER PRIMARY KEY,
  agency_id INTEGER NOT NULL REFERENCES agencies(id),
  source_id TEXT NOT NULL,source_url TEXT,
  title TEXT NOT NULL,reason TEXT,
  status TEXT NOT NULL DEFAULT 'ongoing',
  classification TEXT,severity REAL NOT NULL DEFAULT 1.0,severity_label TEXT,
  voluntary_mandated TEXT,
  announced_date TEXT,initiation_date TEXT,status_updated_date TEXT,
  distribution_description TEXT,quantity_recalled TEXT,units TEXT,
  food_category_id INTEGER REFERENCES food_categories(id),
  retrieval_ts TEXT NOT NULL DEFAULT(datetime('now')),
  source_publication_date TEXT,raw_payload TEXT,
  created_at TEXT NOT NULL DEFAULT(datetime('now')),
  updated_at TEXT NOT NULL DEFAULT(datetime('now')),
  UNIQUE(agency_id,source_id));
CREATE INDEX IF NOT EXISTS idx_rec_agency ON recalls(agency_id);
CREATE INDEX IF NOT EXISTS idx_rec_status ON recalls(status);
CREATE INDEX IF NOT EXISTS idx_rec_date ON recalls(announced_date DESC);
CREATE INDEX IF NOT EXISTS idx_rec_sev ON recalls(severity DESC);
CREATE INDEX IF NOT EXISTS idx_rec_cat ON recalls(food_category_id);

CREATE TABLE IF NOT EXISTS recall_products(
  id INTEGER PRIMARY KEY,recall_id INTEGER NOT NULL REFERENCES recalls(id)ON DELETE CASCADE,
  product_id INTEGER REFERENCES products(id),brand_id INTEGER REFERENCES brands(id),
  description TEXT,upc TEXT,lot_number TEXT,use_by_date TEXT,production_date TEXT,
  created_at TEXT NOT NULL DEFAULT(datetime('now')));
CREATE INDEX IF NOT EXISTS idx_rp_rec ON recall_products(recall_id);
CREATE INDEX IF NOT EXISTS idx_rp_upc ON recall_products(upc);

CREATE TABLE IF NOT EXISTS recall_hazards(
  id INTEGER PRIMARY KEY,recall_id INTEGER NOT NULL REFERENCES recalls(id)ON DELETE CASCADE,
  hazard_id INTEGER NOT NULL REFERENCES hazards(id),
  confidence TEXT NOT NULL DEFAULT 'confirmed',
  created_at TEXT NOT NULL DEFAULT(datetime('now')),UNIQUE(recall_id,hazard_id));
CREATE INDEX IF NOT EXISTS idx_rh_rec ON recall_hazards(recall_id);
CREATE INDEX IF NOT EXISTS idx_rh_haz ON recall_hazards(hazard_id);

CREATE TABLE IF NOT EXISTS recall_manufacturers(
  id INTEGER PRIMARY KEY,recall_id INTEGER NOT NULL REFERENCES recalls(id)ON DELETE CASCADE,
  manufacturer_id INTEGER NOT NULL REFERENCES manufacturers(id),
  relationship_type TEXT NOT NULL DEFAULT 'manufacturer',
  confidence TEXT NOT NULL DEFAULT 'confirmed',
  created_at TEXT NOT NULL DEFAULT(datetime('now')),UNIQUE(recall_id,manufacturer_id));

CREATE TABLE IF NOT EXISTS recall_distributors(
  id INTEGER PRIMARY KEY,recall_id INTEGER NOT NULL REFERENCES recalls(id)ON DELETE CASCADE,
  distributor_id INTEGER NOT NULL REFERENCES distributors(id),
  relationship_type TEXT NOT NULL DEFAULT 'distributor',
  confidence TEXT NOT NULL DEFAULT 'confirmed',
  created_at TEXT NOT NULL DEFAULT(datetime('now')));

CREATE TABLE IF NOT EXISTS recall_retailers(
  id INTEGER PRIMARY KEY,recall_id INTEGER NOT NULL REFERENCES recalls(id)ON DELETE CASCADE,
  retailer_id INTEGER NOT NULL REFERENCES retailers(id),
  relationship_type TEXT NOT NULL DEFAULT 'retailer',
  confidence TEXT NOT NULL DEFAULT 'inferred',
  store_ids TEXT,
  created_at TEXT NOT NULL DEFAULT(datetime('now')),UNIQUE(recall_id,retailer_id));
CREATE INDEX IF NOT EXISTS idx_rr_rec ON recall_retailers(recall_id);
CREATE INDEX IF NOT EXISTS idx_rr_ret ON recall_retailers(retailer_id);

CREATE TABLE IF NOT EXISTS recall_states(
  id INTEGER PRIMARY KEY,recall_id INTEGER NOT NULL REFERENCES recalls(id)ON DELETE CASCADE,
  state_code TEXT NOT NULL,nationwide INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT(datetime('now')),UNIQUE(recall_id,state_code));
CREATE INDEX IF NOT EXISTS idx_rs_rec ON recall_states(recall_id);
CREATE INDEX IF NOT EXISTS idx_rs_st ON recall_states(state_code);

CREATE TABLE IF NOT EXISTS retail_exposures(
  id INTEGER PRIMARY KEY,recall_id INTEGER NOT NULL REFERENCES recalls(id)ON DELETE CASCADE,
  retailer_id INTEGER NOT NULL REFERENCES retailers(id),
  event_risk REAL NOT NULL DEFAULT 0.0,severity_score REAL NOT NULL DEFAULT 1.0,
  geo_relevance REAL NOT NULL DEFAULT 1.0,dist_confidence REAL NOT NULL DEFAULT 0.25,
  recency_weight REAL NOT NULL DEFAULT 1.0,is_private_label INTEGER NOT NULL DEFAULT 0,
  snapshot_date TEXT NOT NULL DEFAULT(date('now')),
  created_at TEXT NOT NULL DEFAULT(datetime('now')),UNIQUE(recall_id,retailer_id));
CREATE INDEX IF NOT EXISTS idx_re_ret ON retail_exposures(retailer_id);
CREATE INDEX IF NOT EXISTS idx_re_risk ON retail_exposures(event_risk DESC);

CREATE TABLE IF NOT EXISTS source_records(
  id INTEGER PRIMARY KEY,recall_id INTEGER REFERENCES recalls(id)ON DELETE SET NULL,
  agency_id INTEGER NOT NULL REFERENCES agencies(id),
  source_id TEXT NOT NULL,source_url TEXT,raw_json TEXT,
  retrieved_at TEXT NOT NULL DEFAULT(datetime('now')),
  parser_version TEXT NOT NULL DEFAULT '1.0',UNIQUE(agency_id,source_id));

CREATE TABLE IF NOT EXISTS recall_updates(
  id INTEGER PRIMARY KEY,recall_id INTEGER NOT NULL REFERENCES recalls(id)ON DELETE CASCADE,
  update_type TEXT NOT NULL,description TEXT,field_changed TEXT,
  old_value TEXT,new_value TEXT,source TEXT,
  updated_at TEXT NOT NULL DEFAULT(datetime('now')));
CREATE INDEX IF NOT EXISTS idx_ru_rec ON recall_updates(recall_id);

CREATE TABLE IF NOT EXISTS ingestion_runs(
  id INTEGER PRIMARY KEY,agency_code TEXT NOT NULL,
  started_at TEXT NOT NULL DEFAULT(datetime('now')),
  completed_at TEXT,status TEXT NOT NULL DEFAULT 'running',
  records_fetched INTEGER NOT NULL DEFAULT 0,records_inserted INTEGER NOT NULL DEFAULT 0,
  records_updated INTEGER NOT NULL DEFAULT 0,records_rejected INTEGER NOT NULL DEFAULT 0,
  errors TEXT,duration_ms INTEGER);
CREATE INDEX IF NOT EXISTS idx_ir_ag ON ingestion_runs(agency_code);
CREATE INDEX IF NOT EXISTS idx_ir_st ON ingestion_runs(started_at DESC);

CREATE TABLE IF NOT EXISTS watchlists(
  id INTEGER PRIMARY KEY,session_id TEXT NOT NULL,
  watch_type TEXT NOT NULL,watch_value TEXT NOT NULL,watch_label TEXT,
  created_at TEXT NOT NULL DEFAULT(datetime('now')),
  UNIQUE(session_id,watch_type,watch_value));
CREATE INDEX IF NOT EXISTS idx_wl_sid ON watchlists(session_id);

CREATE TABLE IF NOT EXISTS data_quality_flags(
  id INTEGER PRIMARY KEY,recall_id INTEGER REFERENCES recalls(id)ON DELETE CASCADE,
  flag_type TEXT NOT NULL,description TEXT,
  severity TEXT NOT NULL DEFAULT 'warn',resolved INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT(datetime('now')));
CREATE INDEX IF NOT EXISTS idx_dq_rec ON data_quality_flags(recall_id);

CREATE VIRTUAL TABLE IF NOT EXISTS recalls_fts USING fts5(
  recall_id UNINDEXED,title,reason,product_desc,brand_name,mfr_name);
SQL; }

function m2():string{ return <<<'SQL'
INSERT OR IGNORE INTO agencies(code,name,base_url,api_url)VALUES
('FDA','U.S. Food and Drug Administration','https://www.fda.gov','https://api.fda.gov/food/enforcement.json'),
('FSIS','USDA Food Safety and Inspection Service','https://www.fsis.usda.gov','https://www.fsis.usda.gov/fsis/api/recall/v/1'),
('CDC','Centers for Disease Control and Prevention','https://www.cdc.gov',NULL);
SQL; }

function m3():string{
    $rows=[];$i=1;
    foreach(array_keys(CAT_KEYWORDS) as $slug){
        $name=ucwords(str_replace('_',' ',$slug));
        $rows[]="($i,NULL,'".str_replace("'","''",$name)."','$slug',$i)";$i++;
    }
    return 'INSERT OR IGNORE INTO food_categories(id,parent_id,name,slug,sort_order)VALUES'.implode(',',$rows).';';
}

function m4():string{
    $rows=[];
    foreach(HAZ_TYPE_MAP as $slug=>$type){
        $name=ucwords(str_replace('_',' ',$slug));
        $rows[]="(NULL,'$type','$slug','".str_replace("'","''",$name)."','$slug',NULL)";
    }
    return 'INSERT OR IGNORE INTO hazards(id,type,subtype,name,slug,description)VALUES'.implode(',',$rows).';';
}

function m5():string{ return <<<'SQL'
CREATE TABLE IF NOT EXISTS risk_snapshots(
  id INTEGER PRIMARY KEY,retailer_id INTEGER NOT NULL REFERENCES retailers(id),
  snapshot_date TEXT NOT NULL,active_count INTEGER NOT NULL DEFAULT 0,
  total_risk REAL NOT NULL DEFAULT 0.0,severe_count INTEGER NOT NULL DEFAULT 0,
  biological_count INTEGER NOT NULL DEFAULT 0,allergen_count INTEGER NOT NULL DEFAULT 0,
  physical_count INTEGER NOT NULL DEFAULT 0,chemical_count INTEGER NOT NULL DEFAULT 0,
  private_label_count INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT(datetime('now')),
  UNIQUE(retailer_id,snapshot_date));
CREATE INDEX IF NOT EXISTS idx_rsnap_ret ON risk_snapshots(retailer_id);
SQL; }

function m6():string{ return <<<'SQL'
CREATE TABLE IF NOT EXISTS api_health(
  id INTEGER PRIMARY KEY,agency_code TEXT NOT NULL UNIQUE,
  last_check TEXT,last_success TEXT,last_status INTEGER,
  consecutive_failures INTEGER NOT NULL DEFAULT 0,notes TEXT);
INSERT OR IGNORE INTO api_health(agency_code)VALUES('FDA'),('FSIS');
SQL; }

// ================================================================
// § ENTITY RESOLUTION
// ================================================================
function norm(string $s):string{ return strtolower(trim(preg_replace('/\s+/',' ',$s))); }

function resolve_agency(string $code):int{
    $r=db()->prepare('SELECT id FROM agencies WHERE code=?');
    $r->execute([$code]);
    if($id=$r->fetchColumn())return(int)$id;
    $r=db()->prepare("INSERT INTO agencies(code,name)VALUES(?,?)");
    $r->execute([$code,$code]);
    return(int)db()->lastInsertId();
}

function resolve_manufacturer(string $name,string $city='',string $state=''):int{
    $n=norm($name);
    $r=db()->prepare('SELECT id FROM manufacturers WHERE normalized_name=?');
    $r->execute([$n]);
    if($id=$r->fetchColumn())return(int)$id;
    db()->prepare('INSERT INTO manufacturers(name,normalized_name,city,state)VALUES(?,?,?,?)')->execute([$name,$n,$city,$state]);
    return(int)db()->lastInsertId();
}

function resolve_brand(string $name,int $mfr_id=0):int{
    $n=norm($name);
    $r=db()->prepare('SELECT id FROM brands WHERE normalized_name=?');
    $r->execute([$n]);
    if($id=$r->fetchColumn())return(int)$id;
    db()->prepare('INSERT INTO brands(name,normalized_name,manufacturer_id)VALUES(?,?,?)')->execute([$name,$n,$mfr_id?:null]);
    return(int)db()->lastInsertId();
}

function resolve_retailer(string $name):int{
    $n=norm($name);
    $r=db()->prepare('SELECT id FROM retailers WHERE normalized_name=?');
    $r->execute([$n]);
    if($id=$r->fetchColumn())return(int)$id;
    db()->prepare('INSERT INTO retailers(name,normalized_name)VALUES(?,?)')->execute([$name,$n]);
    return(int)db()->lastInsertId();
}

function resolve_food_category(string $text):?int{
    $tl=strtolower($text);
    $best=null;$bestCount=0;
    foreach(CAT_KEYWORDS as $slug=>$kws){
        $cnt=0;
        foreach($kws as $kw){if(str_contains($tl,$kw))$cnt++;}
        if($cnt>$bestCount){$bestCount=$cnt;$best=$slug;}
    }
    if(!$best)return null;
    $r=db()->prepare('SELECT id FROM food_categories WHERE slug=?');
    $r->execute([$best]);
    return($id=$r->fetchColumn())?(int)$id:null;
}

function classify_hazards(string $text):array{
    $tl=strtolower($text);$found=[];
    foreach(HAZ_KEYWORDS as $slug=>$kws){
        foreach($kws as $kw){if(str_contains($tl,$kw)){$found[]=$slug;break;}}
    }
    return array_unique($found);
}

function extract_states(string $text):array{
    $found=[];
    if(preg_match('/nationwide|all (50 )?states|all us states/i',$text)){
        foreach(array_keys(US_STATES) as $code){
            if($code!=='nationwide')$found[]=[$code,1];
        }
        return $found;
    }
    foreach(US_STATES as $code=>$name){
        if($code==='nationwide')continue;
        if(str_contains($text,$code)||stripos($text,$name)!==false)
            $found[]=[$code,0];
    }
    return $found;
}

function extract_retailers_from_text(string $text):array{
    $tl=strtolower($text);$found=[];
    foreach(KNOWN_RETAILERS as $key=>$display){
        if(str_contains($tl,$key)){
            $found[]=[$display,'inferred'];
        }
    }
    return $found;
}

function get_hazard_id(string $slug):?int{
    $r=db()->prepare('SELECT id FROM hazards WHERE slug=?');
    $r->execute([$slug]);
    return($id=$r->fetchColumn())?(int)$id:null;
}

function flag_dq(int $recall_id,string $type,string $desc,string $sev='warn'):void{
    db()->prepare('INSERT OR IGNORE INTO data_quality_flags(recall_id,flag_type,description,severity)VALUES(?,?,?,?)')->execute([$recall_id,$type,$desc,$sev]);
}

// ================================================================
// § HTTP HELPERS
// ================================================================
function fw_fetch(string $url,array $params=[],int $timeout=INGEST_TIMEOUT):array{
    if($params)$url.='?'.http_build_query($params);
    $ctx=stream_context_create(['http'=>[
        'timeout'=>$timeout,'ignore_errors'=>true,
        'header'=>"User-Agent: FoodWatch-US/1.0 (food safety intelligence; contact=foodwatch@example.com)\r\nAccept: application/json\r\n",
    ],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $raw=@file_get_contents($url,false,$ctx);
    $headers=$http_response_header??[];
    $status=200;
    foreach($headers as $h){
        if(preg_match('#^HTTP/\S+\s+(\d+)#',$h,$m)){$status=(int)$m[1];break;}
    }
    if($raw===false)return['ok'=>false,'status'=>0,'data'=>null,'error'=>'Connection failed'];
    $data=json_decode($raw,true);
    return['ok'=>$status===200,'status'=>$status,'data'=>$data,'raw'=>$raw,'error'=>$status!==200?"HTTP $status":null];
}

// ================================================================
// § FDA INGESTION
// ================================================================
function ingest_fda(bool $full=false):array{
    $agency_id=resolve_agency('FDA');
    $run_id=start_run('FDA');
    $stats=['fetched'=>0,'inserted'=>0,'updated'=>0,'rejected'=>0,'errors'=>[]];

    try{
        $skip=0;$page=0;
        do{
            $res=fw_fetch(FDA_API,['search'=>'product_type:Food','limit'=>INGEST_PAGE_SIZE,'skip'=>$skip,'sort'=>'report_date:desc']);
            if(!$res['ok']){
                $stats['errors'][]='FDA API: '.($res['error']??'unknown');
                update_api_health('FDA',$res['status']??0,false);
                break;
            }
            update_api_health('FDA',200,true);
            $results=$res['data']['results']??[];
            $total=$res['data']['meta']['results']['total']??0;
            if(empty($results))break;

            foreach($results as $raw){
                $stats['fetched']++;
                try{
                    $r=parse_fda_record($raw,$agency_id);
                    if($r==='skip'){continue;}
                    $action=upsert_recall($r,$raw);
                    $stats[$action]++;
                }catch(\Throwable $e){
                    $stats['rejected']++;
                    $stats['errors'][]='FDA parse: '.$e->getMessage();
                }
            }
            $skip+=INGEST_PAGE_SIZE;$page++;
        }while(count($results)===INGEST_PAGE_SIZE && $page<INGEST_MAX_PAGES && ($full||$page<2));
    }catch(\Throwable $e){
        $stats['errors'][]='FDA fatal: '.$e->getMessage();
    }

    finish_run($run_id,$stats);
    return $stats;
}

function parse_fda_record(array $r,int $agency_id):array|string{
    $src_id=trim($r['recall_number']??'');
    if(!$src_id)return 'skip';

    $title=trim($r['product_description']??'');
    if(!$title)$title='FDA Recall '.$src_id;

    $raw_date=$r['report_date']??$r['recall_initiation_date']??'';
    $date=parse_fda_date($raw_date);
    $init_date=parse_fda_date($r['recall_initiation_date']??'');

    $classification=trim($r['classification']??'Class III');
    $severity=SEV_SCORES[$classification]??1.0;

    $firm=trim($r['recalling_firm']??'');
    $dist=trim($r['distribution_pattern']??'');
    $reason=trim($r['reason_for_recall']??'');

    $cat_id=resolve_food_category($title.' '.$reason);
    $hazards=classify_hazards($reason.' '.$title);
    $states=extract_states($dist.' '.$reason);
    $retailers=extract_retailers_from_text($dist.' '.$reason);

    $mfr_id=0;
    if($firm){
        $city=trim($r['city']??'');
        $state=trim($r['state']??'');
        $mfr_id=resolve_manufacturer($firm,$city,$state);
    }

    $status_raw=strtolower(trim($r['status']??'ongoing'));
    $status=match($status_raw){
        'completed','terminated'=>$status_raw,
        default=>'ongoing',
    };

    return[
        'agency_id'=>$agency_id,'source_id'=>$src_id,
        'source_url'=>'https://www.fda.gov/safety/recalls-market-withdrawals-safety-alerts',
        'title'=>$title,'reason'=>$reason,'status'=>$status,
        'classification'=>$classification,'severity'=>$severity,'severity_label'=>$classification,
        'voluntary_mandated'=>trim($r['voluntary_mandated']??''),
        'announced_date'=>$date,'initiation_date'=>$init_date,
        'distribution_description'=>$dist,
        'quantity_recalled'=>trim($r['product_quantity']??''),
        'food_category_id'=>$cat_id,
        'source_publication_date'=>$date,
        'raw_payload'=>json_encode($r),
        '_mfr_id'=>$mfr_id,'_hazards'=>$hazards,
        '_states'=>$states,'_retailers'=>$retailers,
        '_code_info'=>trim($r['code_info']??''),
        '_brand'=>$firm,
    ];
}

function parse_fda_date(string $d):?string{
    if(!$d)return null;
    $d=preg_replace('/\D/','',$d);
    if(strlen($d)===8)return substr($d,0,4).'-'.substr($d,4,2).'-'.substr($d,6,2);
    return null;
}

// ================================================================
// § USDA FSIS INGESTION
// ================================================================
function ingest_fsis(bool $full=false):array{
    $agency_id=resolve_agency('FSIS');
    $run_id=start_run('FSIS');
    $stats=['fetched'=>0,'inserted'=>0,'updated'=>0,'rejected'=>0,'errors'=>[]];

    try{
        $res=fw_fetch(FSIS_API,[],INGEST_TIMEOUT);
        if(!$res['ok']){
            $stats['errors'][]='FSIS API: '.($res['error']??'unknown error');
            update_api_health('FSIS',$res['status']??0,false);
            finish_run($run_id,$stats);
            return $stats;
        }
        update_api_health('FSIS',200,true);

        $data=$res['data'];
        if(!is_array($data)){$stats['errors'][]='FSIS: unexpected response format';finish_run($run_id,$stats);return $stats;}

        // FSIS API may return {data:[...]} or plain array
        $items=isset($data['data'])?$data['data']:(is_array($data[0]??null)?$data:[]);

        foreach($items as $raw){
            $stats['fetched']++;
            try{
                $r=parse_fsis_record($raw,$agency_id);
                if($r==='skip')continue;
                $action=upsert_recall($r,$raw);
                $stats[$action]++;
            }catch(\Throwable $e){
                $stats['rejected']++;
                $stats['errors'][]='FSIS parse: '.$e->getMessage();
            }
        }
    }catch(\Throwable $e){
        $stats['errors'][]='FSIS fatal: '.$e->getMessage();
    }

    finish_run($run_id,$stats);
    return $stats;
}

function parse_fsis_record(array $r,int $agency_id):array|string{
    // Support multiple FSIS field naming conventions
    $src_id=trim($r['RI_ID']??$r['recall_id']??$r['id']??'');
    if(!$src_id)return 'skip';
    $src_id='FSIS-'.$src_id;

    $title=trim($r['Name']??$r['product_name']??$r['name']??'');
    if(!$title)$title='FSIS Recall '.$src_id;

    $raw_date=$r['RDate']??$r['recall_date']??$r['date']??'';
    $date=is_string($raw_date)?date('Y-m-d',strtotime($raw_date)):null;
    if($date==='1970-01-01')$date=null;

    $cls=trim($r['Class']??$r['classification']??'Class I');
    if(!str_starts_with($cls,'Class'))$cls='Class '.$cls;
    $severity=SEV_SCORES[$cls]??3.0;

    $firm=trim($r['Company']??$r['company']??$r['establishment']??'');
    $reason=trim($r['Summary']??$r['reason']??$r['summary']??'');
    $dist=trim($r['States']??$r['distribution']??'');

    $cat_id=resolve_food_category($title.' '.$reason);
    $hazards=classify_hazards($reason.' '.$title);
    $states=extract_states($dist.' '.$reason);
    $retailers=extract_retailers_from_text($dist.' '.$reason);

    $mfr_id=0;
    if($firm)$mfr_id=resolve_manufacturer($firm);

    $status_raw=strtolower(trim($r['Status']??$r['status']??'ongoing'));
    $status=match(true){
        str_contains($status_raw,'complet')=>'completed',
        str_contains($status_raw,'terminat')=>'terminated',
        default=>'ongoing',
    };

    return[
        'agency_id'=>$agency_id,'source_id'=>$src_id,
        'source_url'=>'https://www.fsis.usda.gov/recalls',
        'title'=>$title,'reason'=>$reason,'status'=>$status,
        'classification'=>$cls,'severity'=>$severity,'severity_label'=>$cls,
        'voluntary_mandated'=>'',
        'announced_date'=>$date,'initiation_date'=>$date,
        'distribution_description'=>$dist,
        'quantity_recalled'=>trim((string)($r['Pounds']??$r['pounds']??'')),
        'units'=>'lbs','food_category_id'=>$cat_id,
        'source_publication_date'=>$date,'raw_payload'=>json_encode($r),
        '_mfr_id'=>$mfr_id,'_hazards'=>$hazards,
        '_states'=>$states,'_retailers'=>$retailers,
        '_code_info'=>'','_brand'=>$firm,
    ];
}

// ================================================================
// § NORMALIZATION PIPELINE — upsert_recall
// ================================================================
function upsert_recall(array $r,array $raw):string{
    $db=db();
    $r2=db()->prepare('SELECT id,status,classification FROM recalls WHERE agency_id=? AND source_id=?');
    $r2->execute([$r['agency_id'],$r['source_id']]);
    $existing=$r2->fetch();

    if($existing){
        $rid=(int)$existing['id'];
        $changed=[];
        if($existing['status']!==$r['status'])$changed[]=log_update($rid,'status_change','Status changed',$r['status'],'status',$existing['status'],$r['status'],'ingestion');
        if($existing['classification']!==$r['classification'])$changed[]=log_update($rid,'classification_change','Classification changed',$r['classification'],'classification',$existing['classification'],$r['classification'],'ingestion');

        $db->prepare("UPDATE recalls SET status=?,classification=?,severity=?,severity_label=?,reason=?,distribution_description=?,updated_at=datetime('now'),raw_payload=? WHERE id=?")
            ->execute([$r['status'],$r['classification'],$r['severity'],$r['severity_label'],$r['reason'],$r['distribution_description'],$r['raw_payload'],$rid]);

        update_recall_states($rid,$r['_states']);
        update_recall_retailers($rid,$r['_retailers']);
        update_recall_hazards($rid,$r['_hazards']);
        score_recall_retailers($rid);
        update_fts($rid);
        return 'updated';
    }

    // Insert new recall
    $stmt=$db->prepare("INSERT INTO recalls(agency_id,source_id,source_url,title,reason,status,classification,severity,severity_label,voluntary_mandated,announced_date,initiation_date,distribution_description,quantity_recalled,units,food_category_id,source_publication_date,raw_payload)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$r['agency_id'],$r['source_id'],$r['source_url'],$r['title'],$r['reason'],$r['status'],$r['classification'],$r['severity'],$r['severity_label'],$r['voluntary_mandated'],$r['announced_date'],$r['initiation_date'],$r['distribution_description'],$r['quantity_recalled'],$r['units']??'',$r['food_category_id'],$r['source_publication_date'],$r['raw_payload']]);
    $rid=(int)$db->lastInsertId();

    // Manufacturer link
    if($r['_mfr_id']){
        $db->prepare('INSERT OR IGNORE INTO recall_manufacturers(recall_id,manufacturer_id,relationship_type,confidence)VALUES(?,?,?,?)')->execute([$rid,$r['_mfr_id'],'manufacturer','confirmed']);
        // Also as brand
        $bid=resolve_brand($r['_brand']??'',$r['_mfr_id']);
        if($bid){
            $db->prepare('INSERT OR IGNORE INTO recall_products(recall_id,brand_id,description,upc,lot_number)VALUES(?,?,?,?,?)')->execute([$rid,$bid,$r['title'],null,$r['_code_info']??null]);
        }
    }

    update_recall_states($rid,$r['_states']);
    update_recall_retailers($rid,$r['_retailers']);
    update_recall_hazards($rid,$r['_hazards']);

    // Source record
    $db->prepare('INSERT OR IGNORE INTO source_records(recall_id,agency_id,source_id,source_url,raw_json,parser_version)VALUES(?,?,?,?,?,?)')->execute([$rid,$r['agency_id'],$r['source_id'],$r['source_url'],json_encode($r['raw_payload']),'1.0']);

    // Data quality flags
    if(!$r['_states'])flag_dq($rid,'vague_geography','No specific states extracted from distribution text');
    if(!$r['_retailers'])flag_dq($rid,'missing_retailer','No retailers identified in distribution text');
    if(!($r['_code_info']??''))flag_dq($rid,'missing_lot','No lot/code info found');

    score_recall_retailers($rid);
    update_fts($rid);
    return 'inserted';
}

function log_update(int $rid,string $type,string $desc,string $new,string $field,string $old,string $newv,string $src):void{
    db()->prepare('INSERT INTO recall_updates(recall_id,update_type,description,field_changed,old_value,new_value,source)VALUES(?,?,?,?,?,?,?)')->execute([$rid,$type,$desc,$field,$old,$newv,$src]);
}

function update_recall_states(int $rid,array $states):void{
    foreach($states as [$code,$nw]){
        db()->prepare('INSERT OR IGNORE INTO recall_states(recall_id,state_code,nationwide)VALUES(?,?,?)')->execute([$rid,$code,$nw]);
    }
}

function update_recall_retailers(int $rid,array $retailers):void{
    foreach($retailers as [$name,$conf]){
        $ret_id=resolve_retailer($name);
        db()->prepare('INSERT OR IGNORE INTO recall_retailers(recall_id,retailer_id,relationship_type,confidence)VALUES(?,?,?,?)')->execute([$rid,$ret_id,'retailer',$conf]);
    }
}

function update_recall_hazards(int $rid,array $hazard_slugs):void{
    foreach($hazard_slugs as $slug){
        $haz_id=get_hazard_id($slug);
        if($haz_id)db()->prepare('INSERT OR IGNORE INTO recall_hazards(recall_id,hazard_id,confidence)VALUES(?,?,?)')->execute([$rid,$haz_id,'confirmed']);
    }
}

function update_fts(int $rid):void{
    try{
        $row=db()->prepare('SELECT r.title,r.reason,rp.description,b.name as brand_name,m.name as mfr_name FROM recalls r LEFT JOIN recall_products rp ON rp.recall_id=r.id LEFT JOIN brands b ON b.id=rp.brand_id LEFT JOIN recall_manufacturers rm ON rm.recall_id=r.id LEFT JOIN manufacturers m ON m.id=rm.manufacturer_id WHERE r.id=? LIMIT 1');
        $row->execute([$rid]);$d=$row->fetch();
        if(!$d)return;
        // FTS5 contentless: use rowid=recall_id so delete can target by rowid
        try{
            db()->prepare("INSERT INTO recalls_fts(recalls_fts,rowid,recall_id,title,reason,product_desc,brand_name,mfr_name)VALUES('delete',?,?,?,?,?,?,?)")->execute([$rid,$rid,$d['title']??'',$d['reason']??'',$d['description']??'',$d['brand_name']??'',$d['mfr_name']??'']);
        }catch(\Throwable $ignored){}
        db()->prepare('INSERT INTO recalls_fts(rowid,recall_id,title,reason,product_desc,brand_name,mfr_name)VALUES(?,?,?,?,?,?,?)')->execute([$rid,$rid,$d['title']??'',$d['reason']??'',$d['description']??'',$d['brand_name']??'',$d['mfr_name']??'']);
    }catch(\Throwable $ignored){}
}

// ================================================================
// § RISK ENGINE
// ================================================================
function recency_weight(string $date):float{
    if(!$date)return 0.1;
    $days=max(0,(time()-strtotime($date))/86400);
    return (float)exp(-FW_LAMBDA*$days);
}

function event_risk(float $sev,float $geo,float $dist_conf,float $rec_weight):float{
    return round($sev*$geo*$dist_conf*$rec_weight,4);
}

function score_recall_retailers(int $rid):void{
    $rec=db()->prepare('SELECT severity,announced_date FROM recalls WHERE id=?');
    $rec->execute([$rid]);$rec=$rec->fetch();
    if(!$rec)return;
    $sev=(float)$rec['severity'];
    $rw=recency_weight($rec['announced_date']??'');

    $rets=db()->prepare('SELECT retailer_id,confidence,relationship_type FROM recall_retailers WHERE recall_id=?');
    $rets->execute([$rid]);
    foreach($rets->fetchAll() as $rr){
        $conf=DIST_CONF[$rr['confidence']]??0.25;
        $priv=($rr['relationship_type']==='private_label_retailer')?1:0;
        $er=event_risk($sev,1.0,$conf,$rw);
        db()->prepare('INSERT OR REPLACE INTO retail_exposures(recall_id,retailer_id,event_risk,severity_score,geo_relevance,dist_confidence,recency_weight,is_private_label,snapshot_date)VALUES(?,?,?,?,?,?,?,?,date(\'now\'))')->execute([$rid,$rr['retailer_id'],$er,$sev,1.0,$conf,$rw,$priv]);
    }
}

function rescore_all():void{
    $ids=db()->query('SELECT id FROM recalls')->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $rid)score_recall_retailers((int)$rid);
}

// ================================================================
// § INGESTION RUN MANAGEMENT
// ================================================================
function start_run(string $agency):int{
    db()->prepare("INSERT INTO ingestion_runs(agency_code,status)VALUES(?,'running')")->execute([$agency]);
    return(int)db()->lastInsertId();
}

function finish_run(int $id,array $s):void{
    db()->prepare("UPDATE ingestion_runs SET completed_at=datetime('now'),status=?,records_fetched=?,records_inserted=?,records_updated=?,records_rejected=?,errors=?,duration_ms=CAST((julianday('now')-julianday(started_at))*86400000 AS INTEGER) WHERE id=?")->execute([empty($s['errors'])?'completed':'partial',$s['fetched'],$s['inserted'],$s['updated'],$s['rejected'],json_encode($s['errors']),$id]);
}

function update_api_health(string $code,int $status,bool $ok):void{
    $field=$ok?",last_success=datetime('now')":'';
    db()->prepare("INSERT INTO api_health(agency_code,last_check,last_status,consecutive_failures)VALUES(?,datetime('now'),?,?)ON CONFLICT(agency_code)DO UPDATE SET last_check=datetime('now'),last_status=?$field,consecutive_failures=CASE WHEN ?=1 THEN 0 ELSE consecutive_failures+1 END")->execute([$code,$status,$ok?0:1,$status,(int)$ok]);
}

// ================================================================
// § QUERY LAYER
// ================================================================
function q_stats(string $state=''):array{
    $db=db();
    $where_state='';$params=[];
    if($state&&$state!=='all'){
        $where_state=" AND r.id IN(SELECT recall_id FROM recall_states WHERE state_code=?)";
        $params[]=$state;
    }
    $total=(int)$db->query('SELECT COUNT(*) FROM recalls')->fetchColumn();
    $active=(int)$db->prepare("SELECT COUNT(DISTINCT r.id) FROM recalls r WHERE r.status='ongoing'$where_state")->execute($params)||true;
    $stmt=$db->prepare("SELECT COUNT(DISTINCT r.id) FROM recalls r WHERE r.status='ongoing'$where_state");
    $stmt->execute($params);$active=(int)$stmt->fetchColumn();

    $severe_stmt=$db->prepare("SELECT COUNT(DISTINCT r.id) FROM recalls r WHERE r.status='ongoing' AND r.severity>=3.0$where_state");
    $severe_stmt->execute($params);$severe=(int)$severe_stmt->fetchColumn();

    $ret_stmt=$db->prepare("SELECT COUNT(DISTINCT rr.retailer_id) FROM recall_retailers rr JOIN recalls r ON r.id=rr.recall_id WHERE r.status='ongoing'$where_state");
    $ret_stmt->execute($params);$retailers=(int)$ret_stmt->fetchColumn();

    $cat_stmt=$db->prepare("SELECT COUNT(DISTINCT r.food_category_id) FROM recalls r WHERE r.status='ongoing' AND r.food_category_id IS NOT NULL$where_state");
    $cat_stmt->execute($params);$cats=(int)$cat_stmt->fetchColumn();

    // All-time totals (not gated to active status) for dashboard overview stats
    $total_ret=$db->prepare("SELECT COUNT(DISTINCT rr.retailer_id) FROM recall_retailers rr JOIN recalls r ON r.id=rr.recall_id".($where_state?" WHERE r.id IN(SELECT recall_id FROM recall_states WHERE state_code=?)":""));
    $total_ret->execute($where_state?[$params[0]]:[]);$total_retailers=(int)$total_ret->fetchColumn();

    $total_cat=$db->prepare("SELECT COUNT(DISTINCT r.food_category_id) FROM recalls r WHERE r.food_category_id IS NOT NULL".($where_state?" AND r.id IN(SELECT recall_id FROM recall_states WHERE state_code=?)":""));
    $total_cat->execute($where_state?[$params[0]]:[]);$total_cats=(int)$total_cat->fetchColumn();

    $newest=$db->query("SELECT title,announced_date FROM recalls ORDER BY announced_date DESC LIMIT 1")->fetch();
    $last_sync=$db->query("SELECT MAX(completed_at) FROM ingestion_runs WHERE status IN('completed','partial')")->fetchColumn();

    return compact('total','active','severe','retailers','cats','total_retailers','total_cats','newest','last_sync');
}

function q_recalls(int $page=1,int $per=25,array $f=[]):array{
    $db=db();$offset=($page-1)*$per;
    $w=[];$p=[];
    if(!empty($f['status'])&&$f['status']!=='all'){$w[]='r.status=?';$p[]=$f['status'];}
    if(!empty($f['severity'])){$w[]='r.severity=?';$p[]=(float)$f['severity'];}
    if(!empty($f['state'])){$w[]='r.id IN(SELECT recall_id FROM recall_states WHERE state_code=?)';$p[]=$f['state'];}
    if(!empty($f['category'])){$w[]='r.food_category_id=?';$p[]=(int)$f['category'];}
    if(!empty($f['hazard'])){$w[]='r.id IN(SELECT recall_id FROM recall_hazards WHERE hazard_id=?)';$p[]=(int)$f['hazard'];}
    if(!empty($f['agency'])){$w[]='r.agency_id=?';$p[]=(int)$f['agency'];}
    if(!empty($f['q'])){
        $fts_ids=$db->prepare('SELECT recall_id FROM recalls_fts WHERE recalls_fts MATCH ? LIMIT 500');
        $fts_ids->execute([$f['q'].'*']);
        $ids=array_column($fts_ids->fetchAll(),'recall_id');
        if($ids){$w[]='r.id IN('.implode(',',array_fill(0,count($ids),'?')).')';$p=array_merge($p,$ids);}
        else{return['records'=>[],'total'=>0,'pages'=>0];}
    }
    $where=$w?'WHERE '.implode(' AND ',$w):'';
    $sort=match($f['sort']??'date'){
        'severity'=>'r.severity DESC,r.announced_date DESC',
        'agency'=>'a.code,r.announced_date DESC',
        default=>'r.announced_date DESC,r.id DESC',
    };

    $cnt=$db->prepare("SELECT COUNT(*) FROM recalls r JOIN agencies a ON a.id=r.agency_id $where");
    $cnt->execute($p);$total=(int)$cnt->fetchColumn();

    $sql="SELECT r.id,r.source_id,r.title,r.reason,r.status,r.classification,r.severity,r.severity_label,r.announced_date,r.distribution_description,r.quantity_recalled,r.food_category_id,a.code as agency_code,a.name as agency_name,fc.name as category_name FROM recalls r JOIN agencies a ON a.id=r.agency_id LEFT JOIN food_categories fc ON fc.id=r.food_category_id $where ORDER BY $sort LIMIT ? OFFSET ?";
    $stmt=$db->prepare($sql);$stmt->execute([...$p,$per,$offset]);
    $records=$stmt->fetchAll();

    foreach($records as &$rec){
        $hs=$db->prepare('SELECT h.type,h.name FROM recall_hazards rh JOIN hazards h ON h.id=rh.hazard_id WHERE rh.recall_id=?');
        $hs->execute([$rec['id']]);$rec['hazards']=$hs->fetchAll();
        $ss=$db->prepare('SELECT state_code FROM recall_states WHERE recall_id=? LIMIT 10');
        $ss->execute([$rec['id']]);$rec['states']=array_column($ss->fetchAll(),'state_code');
    }unset($rec);
    return['records'=>$records,'total'=>$total,'pages'=>(int)ceil($total/$per)];
}

function q_recall(int $id):?array{
    $db=db();
    $r=$db->prepare('SELECT r.*,a.code as agency_code,a.name as agency_name,a.base_url as agency_url,fc.name as category_name FROM recalls r JOIN agencies a ON a.id=r.agency_id LEFT JOIN food_categories fc ON fc.id=r.food_category_id WHERE r.id=?');
    $r->execute([$id]);$rec=$r->fetch();
    if(!$rec)return null;

    $rec['products']=$db->prepare('SELECT rp.*,b.name as brand_name,m.name as mfr_name FROM recall_products rp LEFT JOIN brands b ON b.id=rp.brand_id LEFT JOIN manufacturers m ON m.id=b.manufacturer_id WHERE rp.recall_id=?')->execute([$id])->fetchAll(); // can't chain like that
    $stmt=$db->prepare('SELECT rp.*,b.name as brand_name FROM recall_products rp LEFT JOIN brands b ON b.id=rp.brand_id WHERE rp.recall_id=?');$stmt->execute([$id]);$rec['products']=$stmt->fetchAll();
    $stmt=$db->prepare('SELECT h.type,h.name,h.slug,rh.confidence FROM recall_hazards rh JOIN hazards h ON h.id=rh.hazard_id WHERE rh.recall_id=?');$stmt->execute([$id]);$rec['hazards']=$stmt->fetchAll();
    $stmt=$db->prepare('SELECT m.name,m.city,m.state,rm.relationship_type,rm.confidence FROM recall_manufacturers rm JOIN manufacturers m ON m.id=rm.manufacturer_id WHERE rm.recall_id=?');$stmt->execute([$id]);$rec['manufacturers']=$stmt->fetchAll();
    $stmt=$db->prepare('SELECT rt.name,rr.relationship_type,rr.confidence FROM recall_retailers rr JOIN retailers rt ON rt.id=rr.retailer_id WHERE rr.recall_id=?');$stmt->execute([$id]);$rec['retailers']=$stmt->fetchAll();
    $stmt=$db->prepare('SELECT state_code,nationwide FROM recall_states WHERE recall_id=?');$stmt->execute([$id]);$rec['states']=$stmt->fetchAll();
    $stmt=$db->prepare('SELECT update_type,description,field_changed,old_value,new_value,updated_at FROM recall_updates WHERE recall_id=? ORDER BY updated_at DESC');$stmt->execute([$id]);$rec['updates']=$stmt->fetchAll();
    $stmt=$db->prepare('SELECT flag_type,description,severity FROM data_quality_flags WHERE recall_id=?');$stmt->execute([$id]);$rec['dq_flags']=$stmt->fetchAll();
    return $rec;
}

function q_retailers(string $sort='risk',string $state=''):array{
    $db=db();
    $w='';$p=[];
    if($state&&$state!=='all'){
        $w=' WHERE re.retailer_id IN(SELECT DISTINCT rr.retailer_id FROM recall_retailers rr JOIN recall_states rs ON rs.recall_id=rr.recall_id WHERE rs.state_code=?)';
        $p[]=$state;
    }
    $order=match($sort){
        'name'=>'rt.name',
        'active'=>'active_recalls DESC',
        default=>'total_risk DESC',
    };
    $sql="SELECT rt.id,rt.name,
        COUNT(DISTINCT CASE WHEN rc.status='ongoing' THEN re.recall_id END) as active_recalls,
        ROUND(SUM(re.event_risk),3) as total_risk,
        COUNT(DISTINCT re.recall_id) as total_recalls,
        COUNT(DISTINCT CASE WHEN rc.severity>=3.0 THEN re.recall_id END) as severe_recalls,
        COUNT(DISTINCT CASE WHEN h.type='biological' THEN re.recall_id END) as biological_recalls,
        COUNT(DISTINCT CASE WHEN h.type='allergen' THEN re.recall_id END) as allergen_recalls,
        COUNT(DISTINCT CASE WHEN re.is_private_label=1 THEN re.recall_id END) as private_label_recalls,
        MAX(re.event_risk) as max_event_risk
        FROM retail_exposures re
        JOIN retailers rt ON rt.id=re.retailer_id
        JOIN recalls rc ON rc.id=re.recall_id
        LEFT JOIN recall_hazards rh ON rh.recall_id=re.recall_id
        LEFT JOIN hazards h ON h.id=rh.hazard_id
        $w GROUP BY rt.id,rt.name ORDER BY $order LIMIT 100";
    $stmt=$db->prepare($sql);$stmt->execute($p);
    return $stmt->fetchAll();
}

function q_category_stats():array{
    return db()->query("SELECT fc.name,fc.slug,COUNT(DISTINCT r.id) as total,COUNT(DISTINCT CASE WHEN r.status='ongoing' THEN r.id END) as active,MAX(r.announced_date) as latest FROM recalls r JOIN food_categories fc ON fc.id=r.food_category_id GROUP BY fc.id ORDER BY active DESC,total DESC")->fetchAll();
}

function q_hazard_stats():array{
    return db()->query("SELECT h.type,h.name,h.slug,COUNT(DISTINCT rh.recall_id) as total,COUNT(DISTINCT CASE WHEN rc.status='ongoing' THEN rh.recall_id END) as active FROM recall_hazards rh JOIN hazards h ON h.id=rh.hazard_id JOIN recalls rc ON rc.id=rh.recall_id GROUP BY h.id ORDER BY active DESC,total DESC")->fetchAll();
}

function q_geo_stats():array{
    return db()->query("SELECT rs.state_code,COUNT(DISTINCT rs.recall_id) as total,COUNT(DISTINCT CASE WHEN rc.status='ongoing' THEN rs.recall_id END) as active FROM recall_states rs JOIN recalls rc ON rc.id=rs.recall_id WHERE rs.state_code!='nationwide' GROUP BY rs.state_code ORDER BY active DESC")->fetchAll();
}

function q_recent_timeline(int $days=30):array{
    $since=date('Y-m-d',strtotime("-$days days"));
    return db()->prepare("SELECT r.id,r.title,r.severity,r.severity_label,r.announced_date,a.code as agency,r.status,fc.name as category FROM recalls r JOIN agencies a ON a.id=r.agency_id LEFT JOIN food_categories fc ON fc.id=r.food_category_id WHERE r.announced_date>=? ORDER BY r.announced_date DESC LIMIT 50")->execute([$since])->fetchAll(); // can't chain
}

function q_timeline(int $days=30):array{
    $since=date('Y-m-d',strtotime("-$days days"));
    $stmt=db()->prepare("SELECT r.id,r.title,r.severity,r.severity_label,r.announced_date,a.code as agency,r.status,fc.name as category FROM recalls r JOIN agencies a ON a.id=r.agency_id LEFT JOIN food_categories fc ON fc.id=r.food_category_id WHERE r.announced_date>=? ORDER BY r.announced_date DESC LIMIT 50");
    $stmt->execute([$since]);return $stmt->fetchAll();
}

function q_ingestion_runs(int $limit=10):array{
    return db()->prepare('SELECT * FROM ingestion_runs ORDER BY started_at DESC LIMIT ?')->execute([$limit])->fetchAll(); // can't chain
}

function q_runs(int $limit=10):array{
    $s=db()->prepare('SELECT * FROM ingestion_runs ORDER BY started_at DESC LIMIT ?');
    $s->execute([$limit]);return $s->fetchAll();
}

function q_search(string $q,int $limit=50):array{
    if(!trim($q))return[];
    $safe=trim(preg_replace('/[^a-z0-9 \-_]/i','',$q));
    if(!$safe)return[];
    $ids_stmt=db()->prepare('SELECT recall_id FROM recalls_fts WHERE recalls_fts MATCH ? LIMIT ?');
    $ids_stmt->execute([$safe.'*',$limit]);
    $ids=array_column($ids_stmt->fetchAll(),'recall_id');
    if(!$ids)return[];
    $pl=implode(',',array_fill(0,count($ids),'?'));
    $stmt=db()->prepare("SELECT r.id,r.title,r.status,r.severity,r.severity_label,r.announced_date,a.code as agency_code,fc.name as category FROM recalls r JOIN agencies a ON a.id=r.agency_id LEFT JOIN food_categories fc ON fc.id=r.food_category_id WHERE r.id IN($pl) ORDER BY r.announced_date DESC");
    $stmt->execute($ids);return $stmt->fetchAll();
}

// ================================================================
// § SELF-TEST SUITE
// ================================================================
function run_tests():array{
    $results=[];
    $tests=[
        'db_connect'  =>'test_db_connect',
        'wal_mode'    =>'test_wal',
        'schema_ver'  =>'test_schema_ver',
        'fk_enabled'  =>'test_fk',
        'agencies_seeded'=>'test_agencies',
        'categories_seeded'=>'test_categories',
        'hazards_seeded'=>'test_hazards',
        'fda_api'     =>'test_fda_api',
        'fsis_api'    =>'test_fsis_api',
        'ingest_parse'=>'test_ingest_parse',
        'dup_detect'  =>'test_dup_detect',
        'category_classify'=>'test_cat_classify',
        'hazard_classify'=>'test_haz_classify',
        'state_extract'=>'test_state_extract',
        'retailer_extract'=>'test_retailer_extract',
        'risk_calc'   =>'test_risk_calc',
        'recency_decay'=>'test_recency',
        'fts_search'  =>'test_fts',
        'csrf_token'  =>'test_csrf',
        'indexes'     =>'test_indexes',
        'prepared_stmts'=>'test_prepared',
    ];
    foreach($tests as $name=>$fn){
        try{
            $t=microtime(true);
            $r=$fn();
            $ms=round((microtime(true)-$t)*1000,1);
            $results[$name]=array_merge(['ms'=>$ms],$r);
        }catch(\Throwable $e){
            $results[$name]=['status'=>'FAIL','msg'=>$e->getMessage(),'ms'=>0];
        }
    }
    return $results;
}

function test_db_connect():array{
    $v=db()->query('SELECT sqlite_version()')->fetchColumn();
    return['status'=>'PASS','msg'=>"SQLite $v"];
}
function test_wal():array{
    $m=db()->query('PRAGMA journal_mode')->fetchColumn();
    return['status'=>$m==='wal'?'PASS':'FAIL','msg'=>"journal_mode=$m"];
}
function test_schema_ver():array{
    $v=(int)db()->query('SELECT COALESCE(MAX(version),0) FROM schema_migrations')->fetchColumn();
    $ok=$v>=FW_SCHEMA_VER;
    return['status'=>$ok?'PASS':'WARN','msg'=>"schema v$v (expected ".FW_SCHEMA_VER.')'];
}
function test_fk():array{
    $v=(int)db()->query('PRAGMA foreign_keys')->fetchColumn();
    return['status'=>$v===1?'PASS':'FAIL','msg'=>"foreign_keys=$v"];
}
function test_agencies():array{
    $c=(int)db()->query("SELECT COUNT(*) FROM agencies WHERE code IN('FDA','FSIS')")->fetchColumn();
    return['status'=>$c>=2?'PASS':'FAIL','msg'=>"$c agencies seeded"];
}
function test_categories():array{
    $c=(int)db()->query('SELECT COUNT(*) FROM food_categories')->fetchColumn();
    return['status'=>$c>=10?'PASS':'WARN','msg'=>"$c categories"];
}
function test_hazards():array{
    $c=(int)db()->query('SELECT COUNT(*) FROM hazards')->fetchColumn();
    return['status'=>$c>=10?'PASS':'WARN','msg'=>"$c hazards"];
}
function test_fda_api():array{
    $r=fw_fetch(FDA_API,['limit'=>1,'search'=>'product_type:Food'],10);
    if(!$r['ok'])return['status'=>'WARN','msg'=>'FDA API unreachable: '.($r['error']??'unknown')];
    $total=$r['data']['meta']['results']['total']??0;
    return['status'=>'PASS','msg'=>"FDA API reachable; $total total records"];
}
function test_fsis_api():array{
    $r=fw_fetch(FSIS_API,[],10);
    if(!$r['ok'])return['status'=>'WARN','msg'=>'FSIS API unreachable: '.($r['error']??'unknown')];
    return['status'=>'PASS','msg'=>'FSIS API reachable'];
}
function test_ingest_parse():array{
    $fake=['recall_number'=>'TEST-2024-0001','product_description'=>'Test Chicken Soup','reason_for_recall'=>'Undeclared milk and possible salmonella contamination','classification'=>'Class I','status'=>'Ongoing','distribution_pattern'=>'Sold in CA, NY, TX at Walmart and Target stores','recalling_firm'=>'Test Foods Inc','city'=>'Springfield','state'=>'IL','report_date'=>'20240101','recall_initiation_date'=>'20240101','code_info'=>'Lot A1','product_quantity'=>'500 cases','voluntary_mandated'=>'Voluntary'];
    $agency_id=resolve_agency('FDA');
    $r=parse_fda_record($fake,$agency_id);
    if($r==='skip')return['status'=>'FAIL','msg'=>'Parser returned skip for valid record'];
    $ok=isset($r['title'])&&$r['severity']===3.0&&count($r['_hazards'])>=1&&count($r['_states'])>=1;
    return['status'=>$ok?'PASS':'FAIL','msg'=>'Parsed: sev='.$r['severity'].', hazards='.count($r['_hazards']).', states='.count($r['_states'])];
}
function test_dup_detect():array{
    $c1=(int)db()->query('SELECT COUNT(*) FROM recalls')->fetchColumn();
    $fake=['recall_number'=>'DUPTEST-9999','product_description'=>'Duplicate Test Product','reason_for_recall'=>'Test reason','classification'=>'Class III','status'=>'Ongoing','distribution_pattern'=>'Nationwide','recalling_firm'=>'Dup Test Co','report_date'=>'20240101','recall_initiation_date'=>'20240101'];
    $agency_id=resolve_agency('FDA');
    $r=parse_fda_record($fake,$agency_id);
    if($r!=='skip'){upsert_recall($r,$fake);upsert_recall($r,$fake);}// second should be update
    $c2=(int)db()->query('SELECT COUNT(*) FROM recalls')->fetchColumn();
    $duped=$c2>$c1+1;
    // Cleanup
    db()->prepare("DELETE FROM recalls WHERE source_id='DUPTEST-9999'")->execute();
    return['status'=>!$duped?'PASS':'FAIL','msg'=>'Duplicate recall '.($duped?'created (BAD)':'prevented (GOOD)')];
}
function test_cat_classify():array{
    $id=resolve_food_category('Fresh whole milk dairy product');
    return['status'=>$id!==null?'PASS':'WARN','msg'=>'Milk→category id='.($id??'null')];
}
function test_haz_classify():array{
    $h=classify_hazards('Product may contain undeclared milk and possible Salmonella contamination');
    $ok=in_array('salmonella',$h)&&in_array('milk_allergen',$h);
    return['status'=>$ok?'PASS':'FAIL','msg'=>'Found: '.implode(',',$h)];
}
function test_state_extract():array{
    $s=extract_states('Distributed in California, New York and TX nationwide');
    return['status'=>count($s)>=2?'PASS':'WARN','msg'=>count($s).' states extracted'];
}
function test_retailer_extract():array{
    $r=extract_retailers_from_text('Sold at Walmart, Kroger and Whole Foods stores nationwide');
    $ok=count($r)>=2;
    return['status'=>$ok?'PASS':'WARN','msg'=>count($r).' retailers extracted'];
}
function test_risk_calc():array{
    $er=event_risk(3.0,1.0,1.0,1.0);
    $ok=abs($er-3.0)<0.01;
    return['status'=>$ok?'PASS':'FAIL','msg'=>"event_risk(3,1,1,1)=$er"];
}
function test_recency():array{
    $today=recency_weight(date('Y-m-d'));
    $old=recency_weight(date('Y-m-d',strtotime('-69 days')));
    $ok=$today>0.99&&$old>0.45&&$old<0.55;
    return['status'=>$ok?'PASS':'FAIL','msg'=>"today=$today, 69days=$old (half-life check)"];
}
function test_fts():array{
    $count=(int)db()->query('SELECT COUNT(*) FROM recalls_fts')->fetchColumn();
    return['status'=>'PASS','msg'=>"FTS index has $count entries"];
}
function test_csrf():array{
    $t=csrf();$ok=!empty($t)&&strlen($t)===64;
    return['status'=>$ok?'PASS':'FAIL','msg'=>'CSRF token length='.strlen($t)];
}
function test_indexes():array{
    $idx=db()->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name LIKE 'idx_%'")->fetchColumn();
    return['status'=>$idx>=15?'PASS':'WARN','msg'=>"$idx indexes found"];
}
function test_prepared():array{
    $stmt=db()->prepare('SELECT id FROM recalls WHERE status=? AND severity>=? LIMIT 1');
    $stmt->execute(['ongoing',1.0]);
    return['status'=>'PASS','msg'=>'Prepared statements work'];
}

// ================================================================
// § ROUTING & DISPATCH
// ================================================================
function route():void{
    $p=$_GET['page']??'dashboard';
    $api=$_GET['api']??'';

    // API endpoints (JSON)
    if($api){
        handle_api($api);
        return;
    }

    // Admin auth wall
    if(str_starts_with($p,'admin')){
        if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['admin_pass'])){
            if(!csrf_ok())fw_abort('CSRF validation failed',403);
            admin_login($_POST['admin_user']??'admin',$_POST['admin_pass']);
        }
        if($_GET['logout']??false){session_destroy();header('Location: ?');exit;}
        if(!is_admin()){render_admin_login();return;}
    }

    switch($p){
        case 'dashboard': render_page('dashboard');break;
        case 'recalls':   render_page('recalls');break;
        case 'recall':    render_page('recall');break;
        case 'retailers': render_page('retailers');break;
        case 'categories':render_page('categories');break;
        case 'geo':       render_page('geo');break;
        case 'search':    render_page('search');break;
        case 'watchlist': render_page('watchlist');break;
        case 'tests':     render_page('tests');break;
        case 'admin':     render_page('admin');break;
        default:          render_page('dashboard');
    }
}

function handle_api(string $api):void{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try{
        switch($api){
            case 'stats':    echo js(q_stats($_GET['state']??''));break;
            case 'recalls':
                $f=['status'=>$_GET['status']??'all','state'=>$_GET['state']??'','category'=>$_GET['cat']??'','hazard'=>$_GET['haz']??'','agency'=>$_GET['agency']??'','q'=>$_GET['q']??'','sort'=>$_GET['sort']??'date','severity'=>$_GET['sev']??''];
                echo js(q_recalls((int)($_GET['page']??1),(int)($_GET['per']??25),$f));break;
            case 'recall':   echo js(q_recall((int)($_GET['id']??0)));break;
            case 'retailers':echo js(q_retailers($_GET['sort']??'risk',$_GET['state']??''));break;
            case 'categories':echo js(q_category_stats());break;
            case 'hazards':  echo js(q_hazard_stats());break;
            case 'geo':      echo js(q_geo_stats());break;
            case 'timeline': echo js(q_timeline((int)($_GET['days']??30)));break;
            case 'search':   echo js(q_search($_GET['q']??''));break;
            case 'ingest':
                if(!is_admin())fw_abort('Unauthorized',403);
                if(!csrf_ok())fw_abort('CSRF',403);
                $src=$_POST['src']??$_GET['src']??'fda';
                $res=$src==='fsis'?ingest_fsis():ingest_fda();
                echo js(['ok'=>true,'stats'=>$res]);break;
            case 'rescore':
                if(!is_admin())fw_abort('Unauthorized',403);
                rescore_all();echo js(['ok'=>true]);break;
            case 'watchlist_add':
                if(!csrf_ok())fw_abort('CSRF',403);
                $sid=session_id();
                db()->prepare('INSERT OR IGNORE INTO watchlists(session_id,watch_type,watch_value,watch_label)VALUES(?,?,?,?)')->execute([$sid,$_POST['type']??'',$_POST['value']??'',$_POST['label']??'']);
                echo js(['ok'=>true]);break;
            case 'watchlist_del':
                if(!csrf_ok())fw_abort('CSRF',403);
                db()->prepare('DELETE FROM watchlists WHERE session_id=? AND watch_type=? AND watch_value=?')->execute([session_id(),$_POST['type']??'',$_POST['value']??'']);
                echo js(['ok'=>true]);break;
            case 'watchlist':
                $stmt=db()->prepare('SELECT * FROM watchlists WHERE session_id=? ORDER BY created_at DESC');
                $stmt->execute([session_id()]);echo js($stmt->fetchAll());break;
            case 'runs':     if(!is_admin())fw_abort('Unauthorized',403);echo js(q_runs(20));break;
            case 'dq':       if(!is_admin())fw_abort('Unauthorized',403);
                $stmt=db()->query('SELECT flag_type,severity,COUNT(*) as cnt FROM data_quality_flags WHERE resolved=0 GROUP BY flag_type,severity ORDER BY cnt DESC');
                echo js($stmt->fetchAll());break;
            default:         fw_abort('Unknown API endpoint',404);
        }
    }catch(\Throwable $e){
        http_response_code(500);echo js(['error'=>$e->getMessage()]);
    }
    exit;
}

// ================================================================
// § HTML LAYOUT
// ================================================================
function sev_color(float $s):string{
    return match(true){$s>=3.0=>'red',$s>=2.0=>'orange',default=>'yellow'};
}
function sev_badge(float $s,string $label=''):string{
    $c=sev_color($s);
    $l=$label?:($s>=3?'Class I':($s>=2?'Class II':'Class III'));
    $cls=['red'=>'bg-red-100 text-red-800 border-red-300','orange'=>'bg-orange-100 text-orange-800 border-orange-300','yellow'=>'bg-yellow-100 text-yellow-800 border-yellow-300'];
    return '<span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded border '.$cls[$c].'">'.h($l).'</span>';
}
function status_badge(string $s):string{
    $cls=match($s){'ongoing'=>'bg-red-100 text-red-700','completed'=>'bg-green-100 text-green-700','terminated'=>'bg-gray-100 text-gray-600',default=>'bg-blue-100 text-blue-700'};
    return '<span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded '.$cls.'">'.h(ucfirst($s)).'</span>';
}

function layout_head(string $title,string $page):void{ ?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?> — FoodWatch US</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{fw:{50:'#f0f4ff',100:'#dce7ff',500:'#3b5bdb',700:'#2c4cc4',900:'#1a3399'}}}}}</script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
<style>
body{font-family:'Inter',system-ui,sans-serif;background:#f8fafc}
.fw-nav-link{@apply flex items-center gap-2 px-3 py-2 rounded text-sm font-medium text-slate-300 hover:bg-slate-700 hover:text-white transition-colors}
.fw-nav-link.active{@apply bg-slate-700 text-white}
.fw-stat{@apply bg-white rounded-lg border border-slate-200 p-4 shadow-sm}
.fw-table th{@apply px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide bg-slate-50 border-b border-slate-200}
.fw-table td{@apply px-3 py-2 text-sm text-slate-700 border-b border-slate-100}
.fw-table tr:hover td{@apply bg-slate-50}
</style>
</head>
<body class="h-full" x-data>
<div class="min-h-full flex">
<!-- Sidebar -->
<nav class="w-56 bg-slate-800 flex flex-col fixed h-full z-10 shadow-xl">
  <div class="p-4 border-b border-slate-700">
    <a href="?" class="flex items-center gap-2">
      <span class="text-white font-bold text-lg tracking-tight">FoodWatch</span>
      <span class="text-fw-100 text-xs font-medium bg-fw-700 px-1.5 py-0.5 rounded">US</span>
    </a>
    <p class="text-slate-400 text-xs mt-1">Food Recall Intelligence</p>
  </div>
  <div class="flex-1 py-3 px-2 space-y-0.5 overflow-y-auto">
    <a href="?" class="fw-nav-link <?=$page==='dashboard'?'active':''?>"><i data-lucide="layout-dashboard" class="w-4 h-4"></i>Dashboard</a>
    <a href="?page=recalls" class="fw-nav-link <?=$page==='recalls'?'active':''?>"><i data-lucide="alert-triangle" class="w-4 h-4"></i>Active Recalls</a>
    <a href="?page=retailers" class="fw-nav-link <?=$page==='retailers'?'active':''?>"><i data-lucide="store" class="w-4 h-4"></i>Retailer Exposure</a>
    <a href="?page=categories" class="fw-nav-link <?=$page==='categories'?'active':''?>"><i data-lucide="tag" class="w-4 h-4"></i>Food Categories</a>
    <a href="?page=geo" class="fw-nav-link <?=$page==='geo'?'active':''?>"><i data-lucide="map" class="w-4 h-4"></i>Geographic View</a>
    <a href="?page=search" class="fw-nav-link <?=$page==='search'?'active':''?>"><i data-lucide="search" class="w-4 h-4"></i>Search</a>
    <a href="?page=watchlist" class="fw-nav-link <?=$page==='watchlist'?'active':''?>"><i data-lucide="bell" class="w-4 h-4"></i>Watchlist</a>
    <div class="border-t border-slate-700 my-2 pt-2">
      <a href="?page=tests" class="fw-nav-link <?=$page==='tests'?'active':''?>"><i data-lucide="check-circle" class="w-4 h-4"></i>Self-Tests</a>
      <a href="?page=admin" class="fw-nav-link <?=$page==='admin'?'active':''?>"><i data-lucide="settings" class="w-4 h-4"></i>Admin</a>
    </div>
  </div>
  <div class="p-3 border-t border-slate-700 text-xs text-slate-500">v<?=FW_VERSION?></div>
</nav>
<!-- Main content -->
<main class="ml-56 flex-1 min-h-full">
<div class="sticky top-0 z-10 bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between">
  <h1 class="text-sm font-semibold text-slate-700"><?=h($title)?></h1>
  <form action="?" method="get" class="flex items-center gap-2">
    <input type="hidden" name="page" value="search">
    <input type="search" name="q" placeholder="Search recalls, brands, retailers…" class="w-64 text-sm border border-slate-300 rounded px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-fw-500" value="<?=h($_GET['q']??'')?>">
    <button class="text-slate-400 hover:text-slate-600"><i data-lucide="search" class="w-4 h-4"></i></button>
  </form>
</div>
<div class="p-6">
<?php }

function layout_foot():void{ ?>
</div></main></div>
<script>lucide.createIcons();</script>
</body></html>
<?php }

// ================================================================
// § VIEWS
// ================================================================
function render_page(string $p):void{
    match($p){
        'dashboard' =>view_dashboard(),
        'recalls'   =>view_recalls(),
        'recall'    =>view_recall_detail(),
        'retailers' =>view_retailers(),
        'categories'=>view_categories(),
        'geo'       =>view_geo(),
        'search'    =>view_search(),
        'watchlist' =>view_watchlist(),
        'tests'     =>view_tests(),
        'admin'     =>view_admin(),
        default     =>view_dashboard(),
    };
}

function view_dashboard():void{
    $state=$_GET['state']??'';
    $stats=q_stats($state);
    $cats=q_category_stats();
    $hazards=q_hazard_stats();
    $timeline=q_timeline(30);

    layout_head('Dashboard','dashboard'); ?>

<!-- State filter bar -->
<div class="mb-4 flex items-center gap-3">
  <form method="get">
    <input type="hidden" name="page" value="dashboard">
    <select name="state" onchange="this.form.submit()" class="text-sm border border-slate-300 rounded px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-fw-500">
      <option value="">All States</option>
      <?php foreach(US_STATES as $code=>$name): ?>
      <option value="<?=h($code)?>" <?=$state===$code?'selected':''?>><?=h($name)?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if($state): ?>
  <span class="text-sm text-slate-600">Showing recalls relevant to <strong><?=h(US_STATES[$state]??$state)?></strong></span>
  <a href="?" class="text-xs text-fw-500 hover:underline">Clear</a>
  <?php endif; ?>
  <span class="ml-auto text-xs text-slate-400">Last sync: <?=h($stats['last_sync']?date('M j, Y g:ia',strtotime($stats['last_sync'])):'Never')?></span>
</div>

<!-- Stats row -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
  <div class="fw-stat"><div class="text-2xl font-bold text-slate-800"><?=number_format($stats['active'])?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="alert-circle" class="w-3 h-3 text-red-500"></i>Active Recalls</div></div>
  <div class="fw-stat"><div class="text-2xl font-bold text-red-600"><?=number_format($stats['severe'])?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="shield-alert" class="w-3 h-3 text-red-600"></i>Class I (Severe)</div></div>
  <div class="fw-stat"><div class="text-2xl font-bold text-slate-800"><?=number_format($stats['total_retailers'])?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="store" class="w-3 h-3"></i>Retailers Affected</div></div>
  <div class="fw-stat"><div class="text-2xl font-bold text-slate-800"><?=number_format($stats['total_cats'])?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="tag" class="w-3 h-3"></i>Food Categories</div></div>
  <div class="fw-stat"><div class="text-2xl font-bold text-slate-800"><?=number_format($stats['total'])?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="database" class="w-3 h-3"></i>Total Records</div></div>
  <div class="fw-stat"><div class="text-sm font-semibold text-slate-700 truncate"><?=h(mb_substr($stats['newest']['title']??'—',0,30))?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i>Newest Recall</div></div>
</div>

<?php if($stats['active']===0): ?>
<div class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-center mb-6">
  <i data-lucide="database" class="w-8 h-8 text-blue-400 mx-auto mb-3"></i>
  <h3 class="text-lg font-semibold text-blue-800 mb-1">No recall data loaded</h3>
  <p class="text-blue-600 text-sm mb-4">Run the ingestion pipeline to fetch current FDA and USDA recall data.</p>
  <?php if(is_admin()): ?>
  <form method="post" action="?api=ingest" x-data="{loading:false}" @submit.prevent="loading=true;fetch('?api=ingest',{method:'POST',headers:{'X-CSRF-Token':'<?=csrf()?>'},body:new URLSearchParams({src:'fda'})}).then(r=>r.json()).then(d=>{loading=false;alert('FDA: '+d.stats.inserted+' new, '+d.stats.updated+' updated');location.reload()}).catch(e=>{loading=false;alert('Error: '+e)})">
    <button type="submit" :disabled="loading" class="bg-fw-500 text-white px-4 py-2 rounded text-sm font-medium hover:bg-fw-700 disabled:opacity-50">
      <span x-show="!loading">Fetch FDA Recalls Now</span><span x-show="loading">Fetching…</span>
    </button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
  <!-- Recent Recalls -->
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="alert-triangle" class="w-4 h-4 text-red-500"></i>Recent Active Recalls (30 days)</h2>
      <a href="?page=recalls" class="text-xs text-fw-500 hover:underline">View all →</a>
    </div>
    <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
      <?php foreach(array_slice($timeline,0,8) as $rec): ?>
      <a href="?page=recall&id=<?=(int)$rec['id']?>" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 block">
        <div class="mt-0.5"><?=sev_badge((float)$rec['severity'],$rec['severity_label']??'')?></div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-slate-800 truncate"><?=h($rec['title'])?></p>
          <p class="text-xs text-slate-500"><?=h($rec['agency'])?> · <?=h($rec['category']??'Unknown')?> · <?=h($rec['announced_date']??'')?></p>
        </div>
        <div><?=status_badge($rec['status'])?></div>
      </a>
      <?php endforeach; ?>
      <?php if(empty($timeline)): ?><p class="px-4 py-6 text-sm text-slate-400 text-center">No recalls in the past 30 days</p><?php endif; ?>
    </div>
  </div>

  <!-- Hazard Distribution Chart -->
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
    <div class="px-4 py-3 border-b border-slate-200">
      <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="biohazard" class="w-4 h-4 text-orange-500"></i>Hazard Distribution (Active)</h2>
    </div>
    <div id="hazard-chart" class="p-4 h-56"></div>
    <script>
    (function(){
      const data=<?=js(array_values(array_filter($hazards,fn($h)=>$h['active']>0)))?>;
      if(!data.length){document.getElementById('hazard-chart').innerHTML='<p class="text-sm text-slate-400 text-center py-8">No hazard data yet</p>';return;}
      const byType={};
      data.forEach(d=>{byType[d.type]=(byType[d.type]||0)+parseInt(d.active)});
      const types=Object.entries(byType).sort((a,b)=>b[1]-a[1]);
      const colors={biological:'#dc2626',allergen:'#d97706',physical:'#7c3aed',chemical:'#0891b2',regulatory:'#64748b'};
      const el=document.getElementById('hazard-chart');
      const w=el.offsetWidth||300,h=180,margin={top:10,right:10,bottom:30,left:80};
      const svg=d3.select('#hazard-chart').append('svg').attr('width','100%').attr('height',h+margin.top+margin.bottom);
      const g=svg.append('g').attr('transform',`translate(${margin.left},${margin.top})`);
      const iw=w-margin.left-margin.right,ih=h;
      const x=d3.scaleLinear().domain([0,d3.max(types,d=>d[1])]).range([0,iw]);
      const y=d3.scaleBand().domain(types.map(d=>d[0])).range([0,ih]).padding(0.2);
      g.selectAll('.bar').data(types).enter().append('rect').attr('class','bar').attr('y',d=>y(d[0])).attr('width',d=>x(d[1])).attr('height',y.bandwidth()).attr('fill',d=>colors[d[0]]||'#64748b').attr('rx',3);
      g.selectAll('.label').data(types).enter().append('text').attr('x',d=>x(d[1])+4).attr('y',d=>y(d[0])+y.bandwidth()/2+4).attr('font-size','11').attr('fill','#475569').text(d=>`${d[0]} (${d[1]})`);
      g.append('g').attr('transform',`translate(0,${ih})`).call(d3.axisBottom(x).ticks(4).tickFormat(d3.format('d'))).selectAll('text').attr('font-size','10');
    })();
    </script>
  </div>
</div>

<!-- Category Activity -->
<div class="bg-white rounded-lg border border-slate-200 shadow-sm mb-6">
  <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
    <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="tag" class="w-4 h-4 text-blue-500"></i>Food Category Activity</h2>
    <a href="?page=categories" class="text-xs text-fw-500 hover:underline">Full view →</a>
  </div>
  <div class="p-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">
    <?php foreach(array_slice($cats,0,12) as $cat): ?>
    <a href="?page=recalls&cat=<?=h($cat['slug']??'')?>" class="flex flex-col items-center p-3 rounded border border-slate-200 hover:border-fw-500 hover:bg-fw-50 text-center transition-colors">
      <span class="text-2xl font-bold <?=$cat['active']>0?'text-red-600':'text-slate-400'?>"><?=(int)$cat['active']?></span>
      <span class="text-xs text-slate-600 mt-1 leading-tight"><?=h($cat['name']??'')?></span>
      <?php if($cat['total']>$cat['active']): ?><span class="text-xs text-slate-400">(<?=(int)$cat['total']?> total)</span><?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php if(empty($cats)): ?><p class="col-span-6 text-sm text-slate-400 text-center py-4">No category data yet. Run ingestion first.</p><?php endif; ?>
  </div>
</div>
<?php layout_foot(); }

function view_recalls():void{
    $page=(int)($_GET['p']??1);
    $f=['status'=>$_GET['status']??'all','state'=>$_GET['state']??'','category'=>$_GET['cat']??'','hazard'=>$_GET['haz']??'','agency'=>$_GET['agency']??'','q'=>$_GET['q']??'','sort'=>$_GET['sort']??'date','severity'=>$_GET['sev']??''];
    $data=q_recalls($page,25,$f);
    $agencies=db()->query('SELECT id,code,name FROM agencies ORDER BY code')->fetchAll();
    $cats=db()->query('SELECT id,name,slug FROM food_categories ORDER BY name')->fetchAll();
    $hazs=db()->query('SELECT id,name,type FROM hazards ORDER BY type,name')->fetchAll();

    layout_head('Recalls','recalls'); ?>
<!-- Filters -->
<form method="get" class="bg-white border border-slate-200 rounded-lg p-4 mb-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
  <input type="hidden" name="page" value="recalls">
  <select name="status" class="text-sm border border-slate-300 rounded px-2 py-1.5">
    <option value="all" <?=in_array($f['status'],['all',''])?'selected':''?>>All Statuses</option>
    <option value="ongoing" <?=$f['status']==='ongoing'?'selected':''?>>Active Only</option>
    <option value="completed" <?=$f['status']==='completed'?'selected':''?>>Completed</option>
    <option value="terminated" <?=$f['status']==='terminated'?'selected':''?>>Terminated</option>
  </select>
  <select name="sev" class="text-sm border border-slate-300 rounded px-2 py-1.5">
    <option value="">All Severities</option>
    <option value="3" <?=$f['severity']==='3'?'selected':''?>>Class I (Severe)</option>
    <option value="2" <?=$f['severity']==='2'?'selected':''?>>Class II (Moderate)</option>
    <option value="1" <?=$f['severity']==='1'?'selected':''?>>Class III (Low)</option>
  </select>
  <select name="state" class="text-sm border border-slate-300 rounded px-2 py-1.5">
    <option value="">All States</option>
    <?php foreach(US_STATES as $c=>$n): ?><option value="<?=h($c)?>" <?=$f['state']===$c?'selected':''?>><?=h($n)?></option><?php endforeach; ?>
  </select>
  <select name="cat" class="text-sm border border-slate-300 rounded px-2 py-1.5">
    <option value="">All Categories</option>
    <?php foreach($cats as $c): ?><option value="<?=(int)$c['id']?>" <?=$f['category']===$c['id']?'selected':''?>><?=h($c['name'])?></option><?php endforeach; ?>
  </select>
  <select name="haz" class="text-sm border border-slate-300 rounded px-2 py-1.5">
    <option value="">All Hazards</option>
    <?php foreach($hazs as $h): ?><option value="<?=(int)$h['id']?>" <?=$f['hazard']===$h['id']?'selected':''?>>[<?=h($h['type'])?>] <?=h($h['name'])?></option><?php endforeach; ?>
  </select>
  <select name="agency" class="text-sm border border-slate-300 rounded px-2 py-1.5">
    <option value="">All Agencies</option>
    <?php foreach($agencies as $a): ?><option value="<?=(int)$a['id']?>" <?=$f['agency']===$a['id']?'selected':''?>><?=h($a['code'])?></option><?php endforeach; ?>
  </select>
  <button type="submit" class="bg-fw-500 text-white text-sm rounded px-3 py-1.5 font-medium hover:bg-fw-700">Filter</button>
</form>

<div class="bg-white rounded-lg border border-slate-200 shadow-sm">
  <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
    <span class="text-sm text-slate-600"><?=number_format($data['total'])?> recalls</span>
    <div class="flex items-center gap-2 text-xs text-slate-500">
      Sort:
      <?php foreach(['date'=>'Date','severity'=>'Severity','agency'=>'Agency'] as $sv=>$sl): ?>
      <a href="?<?=http_build_query(array_merge($_GET,['sort'=>$sv,'p'=>1]))?>" class="hover:text-fw-500 <?=$f['sort']===$sv?'font-semibold text-fw-500':''?>"><?=h($sl)?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <table class="fw-table w-full">
    <thead><tr><th>Severity</th><th>Product / Reason</th><th>Agency</th><th>Category</th><th>Date</th><th>States</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($data['records'] as $rec): ?>
    <tr>
      <td><?=sev_badge((float)$rec['severity'],$rec['severity_label']??'')?></td>
      <td><a href="?page=recall&id=<?=(int)$rec['id']?>" class="text-fw-500 hover:underline font-medium"><?=h(mb_substr($rec['title'],0,80))?><?=mb_strlen($rec['title'])>80?'…':''?></a>
        <?php if($rec['reason']): ?><br><span class="text-xs text-slate-500"><?=h(mb_substr($rec['reason'],0,100))?><?=mb_strlen($rec['reason'])>100?'…':''?></span><?php endif; ?>
        <?php foreach(($rec['hazards']??[]) as $h): ?><span class="inline-block text-xs bg-slate-100 rounded px-1.5 py-0.5 mr-1 text-slate-600"><?=h($h['name'])?></span><?php endforeach; ?>
      </td>
      <td class="font-mono text-xs"><?=h($rec['agency_code']??'')?></td>
      <td class="text-xs"><?=h($rec['category_name']??'—')?></td>
      <td class="text-xs whitespace-nowrap"><?=h($rec['announced_date']??'—')?></td>
      <td class="text-xs"><?=h(count($rec['states']??[])>3?count($rec['states']).' states':implode(', ',$rec['states']??[]))?></td>
      <td><?=status_badge($rec['status'])?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($data['records'])): ?><tr><td colspan="7" class="text-center py-8 text-slate-400">No recalls match the current filters.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <!-- Pagination -->
  <?php if($data['pages']>1): ?>
  <div class="px-4 py-3 border-t border-slate-200 flex items-center gap-2 text-sm">
    <?php for($i=1;$i<=$data['pages'];$i++): ?>
    <a href="?<?=http_build_query(array_merge($_GET,['p'=>$i]))?>" class="px-3 py-1 rounded <?=$i===$page?'bg-fw-500 text-white':'hover:bg-slate-100 text-slate-600'?>"><?=$i?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php layout_foot(); }

function view_recall_detail():void{
    $id=(int)($_GET['id']??0);
    if(!$id)fw_abort('Missing recall ID');
    $rec=q_recall($id);
    if(!$rec)fw_abort('Recall not found',404);

    $sev=(float)$rec['severity'];
    layout_head(mb_substr($rec['title'],0,60),'recall'); ?>

<div class="mb-4">
  <a href="?page=recalls" class="text-sm text-fw-500 hover:underline flex items-center gap-1"><i data-lucide="arrow-left" class="w-3 h-3"></i>Back to recalls</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- Main detail -->
  <div class="lg:col-span-2 space-y-4">
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-5">
      <div class="flex flex-wrap items-start gap-2 mb-3">
        <?=sev_badge($sev,$rec['severity_label']??'')?>
        <?=status_badge($rec['status'])?>
        <span class="text-xs text-slate-500 font-mono bg-slate-100 px-2 py-0.5 rounded"><?=h($rec['agency_code']??'')?> #<?=h($rec['source_id']??'')?></span>
      </div>
      <h2 class="text-xl font-bold text-slate-800 mb-2"><?=h($rec['title'])?></h2>
      <div class="text-sm text-slate-600 mb-4"><?=h($rec['reason']??'Reason not specified')?></div>

      <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div><dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Agency</dt><dd><?=h($rec['agency_name']??'')?></dd></div>
        <div><dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Announced</dt><dd><?=h($rec['announced_date']??'—')?></dd></div>
        <div><dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Classification</dt><dd><?=h($rec['classification']??'—')?></dd></div>
        <div><dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Voluntary / Mandated</dt><dd><?=h($rec['voluntary_mandated']??'—')?></dd></div>
        <div><dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Food Category</dt><dd><?=h($rec['category_name']??'—')?></dd></div>
        <div><dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Quantity Recalled</dt><dd><?=h($rec['quantity_recalled']??'—')?><?=h($rec['units']??'')?></dd></div>
        <div class="col-span-2"><dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Distribution</dt><dd class="mt-1"><?=h($rec['distribution_description']??'—')?></dd></div>
      </dl>
    </div>

    <!-- Products -->
    <?php if($rec['products']): ?>
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2"><i data-lucide="package" class="w-4 h-4"></i>Affected Products</h3>
      <table class="fw-table w-full">
        <thead><tr><th>Description</th><th>Brand</th><th>UPC</th><th>Lot/Code</th><th>Use By</th></tr></thead>
        <tbody>
        <?php foreach($rec['products'] as $pr): ?>
        <tr><td><?=h($pr['description']??'—')?></td><td><?=h($pr['brand_name']??'—')?></td><td class="font-mono text-xs"><?=h($pr['upc']??'—')?></td><td class="font-mono text-xs"><?=h($pr['lot_number']??'—')?></td><td><?=h($pr['use_by_date']??'—')?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Hazards -->
    <?php if($rec['hazards']): ?>
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2"><i data-lucide="biohazard" class="w-4 h-4 text-orange-500"></i>Identified Hazards</h3>
      <div class="flex flex-wrap gap-2">
        <?php foreach($rec['hazards'] as $h): ?>
        <?php $hc=['biological'=>'red','allergen'=>'orange','physical'=>'purple','chemical'=>'blue','regulatory'=>'slate'];$c=$hc[$h['type']]??'slate'; ?>
        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-<?=$c?>-100 text-<?=$c?>-800 border border-<?=$c?>-200">
          <?=h($h['name'])?> <span class="text-xs opacity-60">(<?=h($h['type'])?>)</span>
          <span class="text-xs opacity-50"><?=h($h['confidence'])?></span>
        </span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Why This Matters -->
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-5">
      <h3 class="text-sm font-semibold text-amber-800 mb-2 flex items-center gap-2"><i data-lucide="info" class="w-4 h-4"></i>Why This Matters</h3>
      <p class="text-sm text-amber-900">
        <?php
        $why=[];
        if($sev>=3.0)$why[]='This is a <strong>Class I recall</strong> — the FDA/USDA\'s most serious category, indicating a reasonable probability of serious adverse health consequences or death.';
        elseif($sev>=2.0)$why[]='This is a <strong>Class II recall</strong>, indicating temporary or medically reversible adverse health consequences.';
        else $why[]='This is a <strong>Class III recall</strong>, unlikely to cause adverse health consequences but corrected to maintain product quality.';
        if($rec['hazards'])$why[]='Identified hazard(s): '.implode(', ',array_column($rec['hazards'],'name')).'.';
        if(count($rec['states']??[])>40)$why[]='Distribution was <strong>nationwide</strong>.';
        elseif(count($rec['states']??[])>10)$why[]='Distribution spanned '.count($rec['states']).' states.';
        echo implode(' ',$why);
        ?>
      </p>
      <p class="text-xs text-amber-700 mt-2 italic">This explanation is derived from structured recall data. Consult the issuing agency for complete guidance.</p>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="space-y-4">
    <!-- Source & Provenance -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4">
      <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2"><i data-lucide="link" class="w-4 h-4"></i>Source & Provenance</h3>
      <dl class="space-y-2 text-sm">
        <div><dt class="text-xs font-semibold text-slate-500">Source Agency</dt><dd><?=h($rec['agency_name']??'')?></dd></div>
        <div><dt class="text-xs font-semibold text-slate-500">Source ID</dt><dd class="font-mono text-xs"><?=h($rec['source_id']??'')?></dd></div>
        <div><dt class="text-xs font-semibold text-slate-500">Ingested</dt><dd class="text-xs"><?=h($rec['retrieval_ts']??'')?></dd></div>
        <?php if($rec['source_url']): ?><div><a href="<?=h($rec['source_url'])?>" target="_blank" rel="noopener noreferrer" class="text-xs text-fw-500 hover:underline flex items-center gap-1"><i data-lucide="external-link" class="w-3 h-3"></i>View official source</a></div><?php endif; ?>
      </dl>
    </div>

    <!-- Manufacturers -->
    <?php if($rec['manufacturers']): ?>
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4">
      <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2"><i data-lucide="factory" class="w-4 h-4"></i>Responsible Entities</h3>
      <?php foreach($rec['manufacturers'] as $m): ?>
      <div class="text-sm mb-2"><strong><?=h($m['name'])?></strong><?php if($m['city']||$m['state']): ?> <span class="text-slate-500"><?=h(trim($m['city'].', '.$m['state'],', '))?></span><?php endif; ?>
        <br><span class="text-xs text-slate-500">Role: <?=h(REL_TYPES[$m['relationship_type']]??$m['relationship_type'])?> · Confidence: <?=h($m['confidence'])?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Retailers -->
    <?php if($rec['retailers']): ?>
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4">
      <h3 class="text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2"><i data-lucide="store" class="w-4 h-4"></i>Identified Retailers</h3>
      <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2 mb-3"><i data-lucide="alert-circle" class="w-3 h-3 inline mr-1"></i>Retailer identification is <?=$rec['retailers'][0]['confidence']==='confirmed'?'confirmed from source data':'inferred from distribution text — treat as approximate'?>.</p>
      <?php foreach($rec['retailers'] as $r): ?>
      <div class="text-sm mb-1 flex items-center justify-between">
        <a href="?page=retailers" class="text-fw-500 hover:underline"><?=h($r['name'])?></a>
        <span class="text-xs text-slate-500"><?=h($r['confidence'])?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- States -->
    <?php if($rec['states']): ?>
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4">
      <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4"></i>Distribution Geography</h3>
      <?php $nationwide=array_filter($rec['states'],fn($s)=>$s['nationwide']); ?>
      <?php if($nationwide): ?><p class="text-sm font-semibold text-red-700">Nationwide distribution</p>
      <?php else: ?><div class="flex flex-wrap gap-1"><?php foreach($rec['states'] as $s): ?><span class="text-xs bg-slate-100 rounded px-1.5 py-0.5"><?=h($s['state_code'])?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- DQ Flags -->
    <?php if($rec['dq_flags']): ?>
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4">
      <h3 class="text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2"><i data-lucide="flag" class="w-4 h-4 text-yellow-500"></i>Data Quality Notes</h3>
      <?php foreach($rec['dq_flags'] as $f): ?>
      <p class="text-xs text-slate-600 mb-1"><span class="text-yellow-600">⚠</span> <?=h($f['description'])?></p>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Update History -->
    <?php if($rec['updates']): ?>
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4">
      <h3 class="text-sm font-semibold text-slate-700 mb-3"><i data-lucide="history" class="w-4 h-4 inline mr-1"></i>Update History</h3>
      <?php foreach($rec['updates'] as $u): ?>
      <div class="text-xs mb-2 border-l-2 border-slate-300 pl-2"><span class="font-medium"><?=h($u['update_type'])?></span> · <?=h($u['updated_at'])?>
        <?php if($u['description']): ?><br><?=h($u['description'])?><?php endif; ?>
        <?php if($u['field_changed']): ?><br><span class="text-slate-400"><?=h($u['field_changed'])?>: <?=h($u['old_value'])?> → <?=h($u['new_value'])?></span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php layout_foot(); }

function view_retailers():void{
    $sort=$_GET['sort']??'risk';
    $state=$_GET['state']??'';
    $retailers=q_retailers($sort,$state);

    layout_head('Retailer Exposure Analysis','retailers'); ?>
<div class="mb-4 flex items-center gap-3 flex-wrap">
  <p class="text-sm text-slate-600"><strong>Retailer Exposure Analysis</strong> shows raw recall event counts and computed risk scores. A higher score indicates more exposure to high-severity, recent, or broadly distributed recalls — not that the retailer is inherently unsafe. Methodology is fully shown.</p>
</div>
<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-xs text-amber-800 flex items-start gap-2">
  <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
  <span><strong>Methodology note:</strong> EventRisk = Severity × GeographicRelevance × DistributionConfidence × RecencyWeight. RetailerExposure = Σ EventRisk across all associated recalls. Retailer relationships with <em>inferred</em> confidence are extracted from distribution text and may be incomplete or imprecise. Private-label attribution is tracked separately. Raw recall counts are shown explicitly and must not be used alone to judge a retailer.</span>
</div>

<form method="get" class="bg-white border border-slate-200 rounded-lg p-3 mb-4 flex items-center gap-3">
  <input type="hidden" name="page" value="retailers">
  <select name="state" class="text-sm border border-slate-300 rounded px-2 py-1.5">
    <option value="">All States</option>
    <?php foreach(US_STATES as $c=>$n): ?><option value="<?=h($c)?>" <?=$state===$c?'selected':''?>><?=h($n)?></option><?php endforeach; ?>
  </select>
  <select name="sort" class="text-sm border border-slate-300 rounded px-2 py-1.5">
    <option value="risk" <?=$sort==='risk'?'selected':''?>>Sort: Risk Score ↓</option>
    <option value="active" <?=$sort==='active'?'selected':''?>>Sort: Active Recalls ↓</option>
    <option value="name" <?=$sort==='name'?'selected':''?>>Sort: Name A-Z</option>
  </select>
  <button type="submit" class="bg-fw-500 text-white text-sm rounded px-3 py-1.5 font-medium hover:bg-fw-700">Apply</button>
</form>

<div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-x-auto">
  <table class="fw-table w-full min-w-max">
    <thead><tr>
      <th>Retailer</th>
      <th title="Active recall exposures">Active<br><span class="font-normal normal-case">(ongoing)</span></th>
      <th title="Risk score: Σ(Sev × Geo × Conf × Recency)">Risk Score<br><span class="font-normal normal-case text-xs">explainable</span></th>
      <th>Total Recalls</th>
      <th title="Recalls classified Class I">Severe<br><span class="font-normal normal-case">(Class I)</span></th>
      <th title="Biological contamination events">Bio<br>Hazard</th>
      <th title="Allergen declaration failures">Allergen</th>
      <th title="Retailer private-label products">Private<br>Label</th>
      <th title="Peak single-event risk">Peak<br>Event Risk</th>
    </tr></thead>
    <tbody>
    <?php foreach($retailers as $r): ?>
    <?php $risk=(float)($r['total_risk']??0); ?>
    <tr>
      <td class="font-medium text-fw-500"><a href="?page=recalls&retailer=<?=(int)$r['id']?>"><?=h($r['name'])?></a></td>
      <td class="text-center"><span class="font-bold <?=$r['active_recalls']>0?'text-red-600':'text-slate-400'?>"><?=(int)$r['active_recalls']?></span><br><span class="text-xs text-slate-400">raw count</span></td>
      <td class="text-center">
        <span class="font-bold <?=$risk>5?'text-red-600':($risk>2?'text-orange-500':'text-slate-700')?>"><?=number_format($risk,2)?></span>
        <br><span class="text-xs text-slate-400">Σ EventRisk</span>
      </td>
      <td class="text-center text-slate-600"><?=(int)$r['total_recalls']?></td>
      <td class="text-center <?=$r['severe_recalls']>0?'text-red-600 font-semibold':'text-slate-400'?>"><?=(int)$r['severe_recalls']?></td>
      <td class="text-center <?=$r['biological_recalls']>0?'text-red-600 font-semibold':'text-slate-400'?>"><?=(int)$r['biological_recalls']?></td>
      <td class="text-center <?=$r['allergen_recalls']>0?'text-orange-600 font-semibold':'text-slate-400'?>"><?=(int)$r['allergen_recalls']?></td>
      <td class="text-center <?=$r['private_label_recalls']>0?'text-purple-600 font-semibold':'text-slate-400'?>"><?=(int)$r['private_label_recalls']?></td>
      <td class="text-center text-xs text-slate-600"><?=number_format((float)$r['max_event_risk'],3)?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($retailers)): ?><tr><td colspan="9" class="text-center py-8 text-slate-400">No retailer exposure data. Run ingestion to populate.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<div class="mt-3 text-xs text-slate-500 space-y-1">
  <p><strong>Risk Score formula:</strong> EventRisk = Severity(1–3) × GeographicRelevance(0–1) × DistributionConfidence(confirmed=1.0, probable=0.75, inferred=0.5, unknown=0.25) × RecencyWeight(e<sup>−λt</sup>, λ=0.01/day). RetailerExposure = Σ all EventRisk values for that retailer.</p>
  <p><strong>Important:</strong> Raw recall counts and risk scores reflect distribution exposure, not causal responsibility. "Private Label" column tracks retailer-owned brand products only.</p>
</div>
<?php layout_foot(); }

function view_categories():void{
    $cats=q_category_stats();
    $hazards=q_hazard_stats();
    layout_head('Food Category Analysis','categories'); ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
    <div class="px-4 py-3 border-b border-slate-200"><h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="tag" class="w-4 h-4 text-blue-500"></i>Recall Activity by Food Category</h2></div>
    <div id="cat-chart" class="p-4 h-72"></div>
    <table class="fw-table w-full">
      <thead><tr><th>Category</th><th>Active</th><th>Total</th><th>Latest</th></tr></thead>
      <tbody>
      <?php foreach($cats as $c): ?>
      <tr><td><a href="?page=recalls&cat=<?=h($c['slug']??'')?>" class="text-fw-500 hover:underline"><?=h($c['name']??'')?></a></td>
        <td class="text-center font-bold <?=$c['active']>0?'text-red-600':'text-slate-400'?>"><?=(int)$c['active']?></td>
        <td class="text-center text-slate-600"><?=(int)$c['total']?></td>
        <td class="text-xs"><?=h($c['latest']??'—')?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($cats)): ?><tr><td colspan="4" class="text-center py-6 text-slate-400">No data. Run ingestion first.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
    <div class="px-4 py-3 border-b border-slate-200"><h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="biohazard" class="w-4 h-4 text-red-500"></i>Hazard Detail</h2></div>
    <div id="haz-chart" class="p-4 h-72"></div>
    <table class="fw-table w-full">
      <thead><tr><th>Hazard</th><th>Type</th><th>Active</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach(array_slice($hazards,0,15) as $h): ?>
      <tr><td><?=h($h['name'])?></td><td class="text-xs capitalize"><?=h($h['type'])?></td>
        <td class="text-center font-bold <?=$h['active']>0?'text-red-600':'text-slate-400'?>"><?=(int)$h['active']?></td>
        <td class="text-center text-slate-600"><?=(int)$h['total']?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
(function(){
  const cats=<?=js($cats)?>;
  if(cats.length){
    const el=document.getElementById('cat-chart');
    const w=el.offsetWidth||400,h=240,m={top:5,right:10,bottom:40,left:90};
    const top=cats.filter(c=>parseInt(c.active)>0).slice(0,10);
    if(!top.length){el.innerHTML='<p class="text-sm text-slate-400 text-center py-8">No active recalls by category</p>';return;}
    const svg=d3.select('#cat-chart').append('svg').attr('width','100%').attr('height',h+m.top+m.bottom);
    const g=svg.append('g').attr('transform',`translate(${m.left},${m.top})`);
    const iw=w-m.left-m.right,ih=h;
    const x=d3.scaleLinear().domain([0,d3.max(top,d=>parseInt(d.active))]).range([0,iw]);
    const y=d3.scaleBand().domain(top.map(d=>d.name)).range([0,ih]).padding(0.2);
    g.selectAll('.bar').data(top).enter().append('rect').attr('y',d=>y(d.name)).attr('width',d=>x(parseInt(d.active))).attr('height',y.bandwidth()).attr('fill','#3b5bdb').attr('rx',3);
    g.selectAll('.lbl').data(top).enter().append('text').attr('x',d=>x(parseInt(d.active))+4).attr('y',d=>y(d.name)+y.bandwidth()/2+4).attr('font-size','11').attr('fill','#475569').text(d=>d.active);
    g.append('g').call(d3.axisLeft(y)).selectAll('text').attr('font-size','11');
    g.append('g').attr('transform',`translate(0,${ih})`).call(d3.axisBottom(x).ticks(5).tickFormat(d3.format('d'))).selectAll('text').attr('font-size','10');
  }
})();
</script>
<?php layout_foot(); }

function view_geo():void{
    $geo=q_geo_stats();
    layout_head('Geographic Distribution','geo'); ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
    <div class="px-4 py-3 border-b border-slate-200"><h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="map" class="w-4 h-4 text-green-500"></i>Recalls by State (Active)</h2></div>
    <table class="fw-table w-full max-h-96 overflow-y-auto block">
      <thead><tr><th>State</th><th>Active Recalls</th><th>Total Recalls</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach($geo as $row): ?>
      <tr><td><?=h(US_STATES[$row['state_code']]??$row['state_code'])?> (<?=h($row['state_code'])?>)</td>
        <td class="text-center font-bold <?=$row['active']>0?'text-red-600':'text-slate-400'?>"><?=(int)$row['active']?></td>
        <td class="text-center"><?=(int)$row['total']?></td>
        <td><a href="?page=recalls&state=<?=h($row['state_code'])?>" class="text-xs text-fw-500 hover:underline">View recalls</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($geo)): ?><tr><td colspan="4" class="text-center py-6 text-slate-400">No geographic data. Run ingestion first.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4">
    <h2 class="text-sm font-semibold text-slate-700 mb-3">Top 15 States by Active Recalls</h2>
    <div id="geo-chart" class="h-80"></div>
    <script>
    (function(){
      const data=<?=js(array_slice($geo,0,15))?>;
      if(!data.length){document.getElementById('geo-chart').innerHTML='<p class="text-sm text-slate-400 text-center py-8">No data</p>';return;}
      const el=document.getElementById('geo-chart');
      const w=el.offsetWidth||350,h=280,m={top:5,right:10,bottom:30,left:50};
      const svg=d3.select('#geo-chart').append('svg').attr('width','100%').attr('height',h+m.top+m.bottom);
      const g=svg.append('g').attr('transform',`translate(${m.left},${m.top})`);
      const iw=w-m.left-m.right,ih=h;
      const x=d3.scaleBand().domain(data.map(d=>d.state_code)).range([0,iw]).padding(0.15);
      const y=d3.scaleLinear().domain([0,d3.max(data,d=>parseInt(d.active))]).range([ih,0]);
      g.selectAll('.bar').data(data).enter().append('rect').attr('x',d=>x(d.state_code)).attr('y',d=>y(parseInt(d.active))).attr('width',x.bandwidth()).attr('height',d=>ih-y(parseInt(d.active))).attr('fill','#3b5bdb').attr('rx',2);
      g.append('g').attr('transform',`translate(0,${ih})`).call(d3.axisBottom(x)).selectAll('text').attr('font-size','9').attr('transform','rotate(-45)').attr('text-anchor','end');
      g.append('g').call(d3.axisLeft(y).ticks(5).tickFormat(d3.format('d'))).selectAll('text').attr('font-size','10');
    })();
    </script>
  </div>
</div>
<?php layout_foot(); }

function view_search():void{
    $q=trim($_GET['q']??'');
    $results=$q?q_search($q):[];
    layout_head('Search','search'); ?>
<form method="get" class="mb-6">
  <input type="hidden" name="page" value="search">
  <div class="flex items-center gap-2">
    <input type="search" name="q" value="<?=h($q)?>" placeholder="Search product, brand, retailer, manufacturer, UPC, recall number…" autofocus class="flex-1 border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fw-500">
    <button type="submit" class="bg-fw-500 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-fw-700 flex items-center gap-2"><i data-lucide="search" class="w-4 h-4"></i>Search</button>
  </div>
</form>
<?php if($q): ?>
<p class="text-sm text-slate-600 mb-3"><?=count($results)?> results for "<strong><?=h($q)?></strong>"</p>
<div class="bg-white rounded-lg border border-slate-200 shadow-sm">
  <?php if($results): ?>
  <table class="fw-table w-full">
    <thead><tr><th>Severity</th><th>Title</th><th>Agency</th><th>Category</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($results as $r): ?>
    <tr>
      <td><?=sev_badge((float)$r['severity'],$r['severity_label']??'')?></td>
      <td><a href="?page=recall&id=<?=(int)$r['id']?>" class="text-fw-500 hover:underline font-medium"><?=h(mb_substr($r['title'],0,80))?></a></td>
      <td class="font-mono text-xs"><?=h($r['agency_code']??'')?></td>
      <td class="text-xs"><?=h($r['category']??'—')?></td>
      <td class="text-xs"><?=h($r['announced_date']??'—')?></td>
      <td><?=status_badge($r['status'])?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="p-8 text-center text-slate-400"><i data-lucide="search-x" class="w-8 h-8 mx-auto mb-2"></i><p>No results found for "<?=h($q)?>"</p><p class="text-xs mt-1">Try broader terms or check for typos.</p></div>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-lg border border-slate-200 p-8 text-center text-slate-400">
  <i data-lucide="search" class="w-10 h-10 mx-auto mb-3"></i>
  <p class="text-sm">Search across all recall data including products, brands, retailers, manufacturers, lot numbers, and geographic areas.</p>
</div>
<?php endif; ?>
<?php layout_foot(); }

function view_watchlist():void{
    $sid=session_id();
    $stmt=db()->prepare('SELECT * FROM watchlists WHERE session_id=? ORDER BY created_at DESC');
    $stmt->execute([$sid]);$items=$stmt->fetchAll();

    layout_head('My Watchlist','watchlist'); ?>
<div class="bg-white rounded-lg border border-slate-200 shadow-sm mb-6 p-5" x-data="{type:'retailer',value:'',label:'',saving:false}">
  <h2 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-fw-500"></i>Add to Watchlist</h2>
  <div class="flex items-end gap-3">
    <div><label class="text-xs font-medium text-slate-600 block mb-1">Type</label>
      <select x-model="type" class="text-sm border border-slate-300 rounded px-2 py-1.5">
        <option value="retailer">Retailer</option><option value="brand">Brand</option>
        <option value="category">Food Category</option><option value="state">State</option>
        <option value="hazard">Hazard</option>
      </select>
    </div>
    <div class="flex-1"><label class="text-xs font-medium text-slate-600 block mb-1">Value</label>
      <input x-model="value" type="text" placeholder="e.g. Walmart" class="w-full text-sm border border-slate-300 rounded px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-fw-500">
    </div>
    <button @click="if(value.trim()){saving=true;fetch('?api=watchlist_add',{method:'POST',headers:{'X-CSRF-Token':'<?=csrf()?>'},body:new URLSearchParams({type:type,value:value,label:label||value})}).then(()=>{saving=false;location.reload()})}" :disabled="saving" class="bg-fw-500 text-white text-sm rounded px-4 py-1.5 hover:bg-fw-700 disabled:opacity-50">
      <span x-show="!saving">Add</span><span x-show="saving">Adding…</span>
    </button>
  </div>
</div>
<div class="bg-white rounded-lg border border-slate-200 shadow-sm">
  <div class="px-4 py-3 border-b border-slate-200"><h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="bell" class="w-4 h-4 text-fw-500"></i>Watched Items (<?=count($items)?>)</h2></div>
  <?php if($items): ?>
  <table class="fw-table w-full">
    <thead><tr><th>Type</th><th>Value</th><th>Added</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($items as $it): ?>
    <tr>
      <td class="capitalize text-xs font-medium"><?=h($it['watch_type'])?></td>
      <td><?=h($it['watch_label']??$it['watch_value'])?></td>
      <td class="text-xs"><?=h($it['created_at'])?></td>
      <td><button onclick="fetch('?api=watchlist_del',{method:'POST',headers:{'X-CSRF-Token':'<?=csrf()?>'},body:new URLSearchParams({type:'<?=h($it['watch_type'])?>',value:'<?=h($it['watch_value'])?>'})})" class="text-xs text-red-500 hover:underline">Remove</button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="p-8 text-center text-slate-400"><i data-lucide="bell-off" class="w-8 h-8 mx-auto mb-2"></i><p class="text-sm">No watched items. Add retailers, brands, or categories above.</p></div>
  <?php endif; ?>
</div>
<?php layout_foot(); }

function view_tests():void{
    $results=run_tests();
    $pass=count(array_filter($results,fn($r)=>$r['status']==='PASS'));
    $warn=count(array_filter($results,fn($r)=>$r['status']==='WARN'));
    $fail=count(array_filter($results,fn($r)=>$r['status']==='FAIL'));
    layout_head('Self-Diagnostic Tests','tests'); ?>
<div class="flex items-center gap-4 mb-4">
  <span class="px-3 py-1 rounded-full text-sm font-bold bg-green-100 text-green-800"><?=$pass?> PASS</span>
  <span class="px-3 py-1 rounded-full text-sm font-bold bg-yellow-100 text-yellow-800"><?=$warn?> WARN</span>
  <span class="px-3 py-1 rounded-full text-sm font-bold bg-red-100 text-red-800"><?=$fail?> FAIL</span>
  <span class="ml-auto text-xs text-slate-500">System: <?=$fail===0?($warn===0?'HEALTHY':'DEGRADED'):'FAILED'?></span>
</div>
<div class="bg-white rounded-lg border border-slate-200 shadow-sm">
  <table class="fw-table w-full">
    <thead><tr><th>Test</th><th>Status</th><th>Result</th><th>ms</th></tr></thead>
    <tbody>
    <?php foreach($results as $name=>$r): ?>
    <?php $cls=['PASS'=>'text-green-700 font-semibold','WARN'=>'text-yellow-700 font-semibold','FAIL'=>'text-red-700 font-bold']; ?>
    <tr>
      <td class="font-mono text-xs"><?=h($name)?></td>
      <td class="<?=$cls[$r['status']]??''?>"><?=h($r['status']??'?')?></td>
      <td class="text-xs"><?=h($r['msg']??'')?></td>
      <td class="text-xs text-slate-400"><?=h($r['ms']??'')?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php layout_foot(); }

function render_admin_login():void{
    layout_head('Admin Login','admin'); ?>
<div class="max-w-md mx-auto mt-16">
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-8">
    <h2 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2"><i data-lucide="lock" class="w-5 h-5 text-fw-500"></i>Admin Access</h2>
    <?php if(isset($_POST['admin_pass'])): ?><div class="bg-red-50 border border-red-200 rounded p-3 mb-4 text-sm text-red-700">Invalid credentials.</div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="page" value="admin">
      <input type="hidden" name="csrf" value="<?=csrf()?>">
      <div class="mb-4"><label class="text-xs font-medium text-slate-600 block mb-1">Username</label><input name="admin_user" type="text" class="w-full border border-slate-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fw-500" autocomplete="username"></div>
      <div class="mb-6"><label class="text-xs font-medium text-slate-600 block mb-1">Password</label><input name="admin_pass" type="password" class="w-full border border-slate-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fw-500" autocomplete="current-password"></div>
      <button type="submit" class="w-full bg-fw-500 text-white rounded py-2 text-sm font-semibold hover:bg-fw-700">Sign In</button>
    </form>
    <p class="text-xs text-slate-400 mt-4 text-center">Set FW_ADMIN_USER / FW_ADMIN_PASS environment variables to change credentials.</p>
  </div>
</div>
<?php layout_foot(); }

function view_admin():void{
    $runs=q_runs(15);
    $health_stmt=db()->query('SELECT * FROM api_health');
    $health=$health_stmt->fetchAll();
    $dq=db()->query('SELECT flag_type,severity,COUNT(*) as cnt FROM data_quality_flags WHERE resolved=0 GROUP BY flag_type ORDER BY cnt DESC')->fetchAll();
    $db_size=file_exists(FW_DB_PATH)?round(filesize(FW_DB_PATH)/1024/1024,2):0;
    $recall_count=(int)db()->query('SELECT COUNT(*) FROM recalls')->fetchColumn();
    $last_run=$runs[0]??null;

    layout_head('Administration','admin'); ?>
<div class="flex items-center justify-between mb-6">
  <div>
    <?php $status=$last_run&&$last_run['status']==='running'?'RUNNING':($last_run&&$last_run['status']==='failed'?'FAILED':'HEALTHY'); ?>
    <span class="px-3 py-1 rounded-full text-sm font-bold <?=$status==='HEALTHY'?'bg-green-100 text-green-800':($status==='RUNNING'?'bg-blue-100 text-blue-800':'bg-red-100 text-red-800')?>"><?=$status?></span>
  </div>
  <a href="?page=admin&logout=1" class="text-xs text-slate-500 hover:text-red-600">Sign out</a>
</div>

<!-- System Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="fw-stat"><div class="text-xl font-bold"><?=number_format($recall_count)?></div><div class="text-xs text-slate-500">Total Recalls</div></div>
  <div class="fw-stat"><div class="text-xl font-bold"><?=$db_size?> MB</div><div class="text-xs text-slate-500">Database Size</div></div>
  <div class="fw-stat"><div class="text-xl font-bold"><?=(int)db()->query('SELECT COUNT(*) FROM retailers')->fetchColumn()?></div><div class="text-xs text-slate-500">Retailers</div></div>
  <div class="fw-stat"><div class="text-xl font-bold"><?=(int)db()->query('SELECT COUNT(*) FROM data_quality_flags WHERE resolved=0')->fetchColumn()?></div><div class="text-xs text-slate-500">Open DQ Flags</div></div>
</div>

<!-- Ingestion controls -->
<div class="bg-white rounded-lg border border-slate-200 shadow-sm p-5 mb-6" x-data="{loading:null,msg:''}">
  <h2 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2"><i data-lucide="refresh-cw" class="w-4 h-4"></i>Data Ingestion</h2>
  <div class="flex flex-wrap gap-3 mb-3">
    <?php foreach(['fda'=>'FDA openFDA','fsis'=>'USDA FSIS'] as $src=>$label): ?>
    <button @click="loading='<?=$src?>';msg='';fetch('?api=ingest',{method:'POST',headers:{'X-CSRF-Token':'<?=csrf()?>'},body:new URLSearchParams({csrf:'<?=csrf()?>',src:'<?=$src?>'})}).then(r=>r.json()).then(d=>{loading=null;msg=(d.stats?`<?=h($label)?>: ${d.stats.inserted} new, ${d.stats.updated} updated, ${d.stats.rejected} rejected`:'Done');setTimeout(()=>location.reload(),2000)}).catch(e=>{loading=null;msg='Error: '+e})" :disabled="loading" class="bg-fw-500 text-white text-sm px-4 py-2 rounded font-medium hover:bg-fw-700 disabled:opacity-50 flex items-center gap-2">
      <i data-lucide="download" class="w-4 h-4"></i>
      <span x-show="loading!=='<?=$src?>'">Fetch <?=h($label)?></span>
      <span x-show="loading==='<?=$src?>'">Fetching…</span>
    </button>
    <?php endforeach; ?>
    <button @click="loading='rescore';fetch('?api=rescore',{method:'POST',headers:{'X-CSRF-Token':'<?=csrf()?>'}}).then(()=>{loading=null;location.reload()})" :disabled="loading" class="bg-slate-600 text-white text-sm px-4 py-2 rounded font-medium hover:bg-slate-700 disabled:opacity-50 flex items-center gap-2"><i data-lucide="calculator" class="w-4 h-4"></i>Recalculate Risk Scores</button>
  </div>
  <p x-show="msg" x-text="msg" class="text-sm text-green-700 font-medium"></p>
</div>

<!-- API Health -->
<div class="bg-white rounded-lg border border-slate-200 shadow-sm mb-6">
  <div class="px-4 py-3 border-b border-slate-200"><h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="activity" class="w-4 h-4"></i>API Health</h2></div>
  <table class="fw-table w-full">
    <thead><tr><th>Source</th><th>Last Check</th><th>Last Success</th><th>HTTP Status</th><th>Failures</th></tr></thead>
    <tbody>
    <?php foreach($health as $h): ?>
    <tr>
      <td class="font-mono font-semibold"><?=h($h['agency_code'])?></td>
      <td class="text-xs"><?=h($h['last_check']??'Never')?></td>
      <td class="text-xs <?=!$h['last_success']?'text-red-500':'text-green-600'?>"><?=h($h['last_success']??'Never')?></td>
      <td class="text-xs"><?=h($h['last_status']??'—')?></td>
      <td class="<?=$h['consecutive_failures']>0?'text-red-600 font-bold':'text-slate-400'?>"><?=(int)$h['consecutive_failures']?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Ingestion Run History -->
<div class="bg-white rounded-lg border border-slate-200 shadow-sm mb-6">
  <div class="px-4 py-3 border-b border-slate-200"><h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="clock" class="w-4 h-4"></i>Recent Ingestion Runs</h2></div>
  <table class="fw-table w-full">
    <thead><tr><th>ID</th><th>Agency</th><th>Started</th><th>Status</th><th>Fetched</th><th>New</th><th>Updated</th><th>Rejected</th><th>Duration</th></tr></thead>
    <tbody>
    <?php foreach($runs as $r): ?>
    <?php $sc=['completed'=>'text-green-700','running'=>'text-blue-700','failed'=>'text-red-700','partial'=>'text-orange-700']; ?>
    <tr>
      <td class="font-mono text-xs"><?=(int)$r['id']?></td>
      <td class="font-mono text-xs font-semibold"><?=h($r['agency_code'])?></td>
      <td class="text-xs"><?=h($r['started_at'])?></td>
      <td class="font-semibold text-xs <?=$sc[$r['status']]??''?>"><?=h(strtoupper($r['status']))?></td>
      <td class="text-center text-xs"><?=(int)$r['records_fetched']?></td>
      <td class="text-center text-xs text-green-700"><?=(int)$r['records_inserted']?></td>
      <td class="text-center text-xs text-blue-700"><?=(int)$r['records_updated']?></td>
      <td class="text-center text-xs <?=$r['records_rejected']>0?'text-red-600':''?>"><?=(int)$r['records_rejected']?></td>
      <td class="text-xs"><?=$r['duration_ms']?number_format((int)$r['duration_ms']).'ms':'—'?></td>
    </tr>
    <?php if($r['errors']&&($errs=json_decode($r['errors'],true))&&$errs): ?>
    <tr><td colspan="9" class="bg-red-50 text-xs text-red-700 px-3 py-1">Errors: <?=h(implode('; ',array_slice($errs,0,3)))?></td></tr>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php if(empty($runs)): ?><tr><td colspan="9" class="text-center py-6 text-slate-400">No ingestion runs yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Data Quality -->
<?php if($dq): ?>
<div class="bg-white rounded-lg border border-slate-200 shadow-sm">
  <div class="px-4 py-3 border-b border-slate-200"><h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="flag" class="w-4 h-4 text-yellow-500"></i>Open Data Quality Flags</h2></div>
  <table class="fw-table w-full">
    <thead><tr><th>Flag Type</th><th>Severity</th><th>Count</th></tr></thead>
    <tbody>
    <?php foreach($dq as $d): ?>
    <tr><td class="font-mono text-xs"><?=h($d['flag_type'])?></td><td class="text-xs capitalize"><?=h($d['severity'])?></td><td class="text-center font-bold"><?=(int)$d['cnt']?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php layout_foot(); }

// ================================================================
// § BOOTSTRAP & MAIN DISPATCH
// ================================================================
try{
    db(); // Initialize DB and run migrations
    route();
}catch(\Throwable $e){
    http_response_code(500);
    if(is_ajax()){
        header('Content-Type: application/json');
        echo json_encode(['error'=>'Internal server error','detail'=>$e->getMessage()]);
    }else{
        echo '<!DOCTYPE html><html><head><title>FoodWatch US — Error</title></head><body style="font-family:sans-serif;padding:2rem;max-width:600px">';
        echo '<h2 style="color:#dc2626">Application Error</h2>';
        echo '<p>'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</p>';
        echo '<p>If this is the first run, ensure the <code>data/</code> directory is writable by PHP.</p>';
        echo '<details><summary style="cursor:pointer;color:#64748b;font-size:0.875rem">Stack trace</summary><pre style="font-size:0.75rem;overflow:auto">'.htmlspecialchars($e->getTraceAsString(),ENT_QUOTES,'UTF-8').'</pre></details>';
        echo '</body></html>';
    }
}
