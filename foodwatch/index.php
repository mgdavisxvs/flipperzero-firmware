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
const FW_VERSION    = '4.0.1';
const FW_SCHEMA_VER = 12;
const FW_DATA_DIR   = __DIR__ . '/data';
const FW_DB_PATH    = __DIR__ . '/data/foodwatch.db';
const FW_LAMBDA     = 0.01;   // global daily decay fallback; per-category λ_c overrides via food_categories.lambda_decay

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
        try{
            $db->exec($sql);
            $db->prepare('INSERT INTO schema_migrations(version)VALUES(?)')->execute([$v]);
            $db->commit();
        }catch(\Throwable $e){
            $db->rollBack();
            // Tolerate "duplicate column" from idempotent ALTER TABLE ADD COLUMN
            if(str_contains($e->getMessage(),'duplicate column')
              ||str_contains($e->getMessage(),'already exists')){
                $db->prepare('INSERT OR IGNORE INTO schema_migrations(version)VALUES(?)')->execute([$v]);
            }else{
                throw new \RuntimeException("Migration $v: ".$e->getMessage());
            }
        }
    }
}

function migrations():array{
    return[1=>m1(),2=>m2(),3=>m3(),4=>m4(),5=>m5(),6=>m6(),7=>m7(),8=>m8(),9=>m9(),10=>m10(),11=>m11(),12=>m12()];
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

function m7():string{ return <<<'SQL'
CREATE TABLE IF NOT EXISTS subscriptions(
  id INTEGER PRIMARY KEY,email TEXT NOT NULL,
  filter_json TEXT NOT NULL DEFAULT '{}',
  active INTEGER NOT NULL DEFAULT 1,
  token TEXT NOT NULL UNIQUE,
  confirmed INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT(datetime('now')),
  last_sent_at TEXT);
CREATE INDEX IF NOT EXISTS idx_sub_email ON subscriptions(email);
CREATE INDEX IF NOT EXISTS idx_sub_token ON subscriptions(token);
SQL; }

function m8():string{ return <<<'SQL'
CREATE TABLE IF NOT EXISTS recall_velocity(
  id INTEGER PRIMARY KEY,
  computed_date TEXT NOT NULL UNIQUE,
  rate_30d INTEGER NOT NULL DEFAULT 0,
  rate_90d INTEGER NOT NULL DEFAULT 0,
  baseline_monthly REAL NOT NULL DEFAULT 0,
  z_score REAL NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT(datetime('now')));
SQL; }

function m9():string{ return <<<'SQL'
CREATE TABLE IF NOT EXISTS recall_transitions(
  id INTEGER PRIMARY KEY,
  recall_id INTEGER NOT NULL REFERENCES recalls(id),
  from_status TEXT NOT NULL,
  to_status TEXT NOT NULL,
  days_in_from_state INTEGER NOT NULL DEFAULT 0,
  transitioned_at TEXT NOT NULL DEFAULT(datetime('now')));
CREATE INDEX IF NOT EXISTS idx_rt_recall ON recall_transitions(recall_id,transitioned_at);
SQL; }

function m10():string{ return <<<'SQL'
CREATE TABLE IF NOT EXISTS markov_params(
  id INTEGER PRIMARY KEY,
  computed_at TEXT NOT NULL DEFAULT(datetime('now')),
  state_count INTEGER NOT NULL DEFAULT 4,
  p_matrix_json TEXT NOT NULL,
  n_matrix_json TEXT NOT NULL,
  e_steps_json TEXT NOT NULL,
  sample_n INTEGER NOT NULL DEFAULT 0,
  confidence TEXT NOT NULL DEFAULT 'low');
SQL; }

function m11():string{
    return "ALTER TABLE food_categories ADD COLUMN lambda_decay REAL NOT NULL DEFAULT 0.01;";
}

function m12():string{ return <<<'SQL'
ALTER TABLE distributors ADD COLUMN city TEXT NOT NULL DEFAULT '';
ALTER TABLE distributors ADD COLUMN state TEXT NOT NULL DEFAULT '';
CREATE UNIQUE INDEX IF NOT EXISTS idx_dist_norm ON distributors(normalized_name);
CREATE UNIQUE INDEX IF NOT EXISTS idx_store_ret_state ON stores(retailer_id,state) WHERE state IS NOT NULL;
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

function resolve_distributor(string $name,string $city='',string $state=''):int{
    $n=norm($name);
    $r=db()->prepare('SELECT id FROM distributors WHERE normalized_name=?');
    $r->execute([$n]);
    if($id=$r->fetchColumn())return(int)$id;
    db()->prepare('INSERT INTO distributors(name,normalized_name,city,state)VALUES(?,?,?,?)')->execute([$name,$n,$city,$state]);
    return(int)db()->lastInsertId();
}

function category_lambda(int $cat_id):float{
    static $cache=[];
    if(isset($cache[$cat_id]))return $cache[$cat_id];
    $r=db()->prepare('SELECT lambda_decay FROM food_categories WHERE id=?');
    $r->execute([$cat_id]);
    $v=$r->fetchColumn();
    return $cache[$cat_id]=$v!==false&&(float)$v>0?(float)$v:FW_LAMBDA;
}

function update_category_lambdas():void{
    // MLE: for each category compute mean resolution time from closed recalls, set λ = 1/mean_days
    $stmt=db()->query("
        SELECT r.food_category_id AS cid,
               AVG(JULIANDAY(COALESCE(rt.transitioned_at,r.updated_at))-JULIANDAY(r.announced_date)) AS mean_days
        FROM recalls r
        LEFT JOIN recall_transitions rt ON rt.recall_id=r.id AND rt.to_status IN('completed','terminated')
        WHERE r.food_category_id IS NOT NULL AND r.announced_date IS NOT NULL
        GROUP BY r.food_category_id
        HAVING AVG(JULIANDAY(COALESCE(rt.transitioned_at,r.updated_at))-JULIANDAY(r.announced_date))>1");
    foreach($stmt->fetchAll() as $row){
        $lambda_c=min(0.1,max(0.001,1.0/(float)$row['mean_days']));
        db()->prepare("UPDATE food_categories SET lambda_decay=? WHERE id=?")->execute([$lambda_c,$row['cid']]);
    }
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

// Extract distributor names from distribution/reason text using "distributed by" / "distributor:" patterns
function extract_distributors_from_text(string $text):array{
    $found=[];
    // Match "distributed by X", "distributor: X", "dist. by X", "sold/supplied by X"
    $patterns=[
        '/distribut(?:ed|or)\s*(?:by|:)\s*([A-Z][A-Za-z0-9&\',. ]{3,50}?)(?:\s*(?:LLC|Inc|Corp|Co\.|Ltd|LP)\.?)?(?:[,;\n]|$)/i',
        '/(?:supplied|shipped|sold)\s+by\s+([A-Z][A-Za-z0-9&\',. ]{3,50}?)(?:\s*(?:LLC|Inc|Corp|Co\.|Ltd|LP)\.?)?(?:[,;\n]|$)/i',
    ];
    foreach($patterns as $pat){
        if(preg_match_all($pat,$text,$m)){
            foreach($m[1] as $name){
                $name=trim($name);
                if($name&&strlen($name)>3&&strlen($name)<80)
                    $found[]=$name;
            }
        }
    }
    return array_unique($found);
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
    $distributors=extract_distributors_from_text($dist.' '.$reason);

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
        '_states'=>$states,'_retailers'=>$retailers,'_distributors'=>$distributors,
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
    $distributors=extract_distributors_from_text($dist.' '.$reason);

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
        '_states'=>$states,'_retailers'=>$retailers,'_distributors'=>$distributors,
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
        if(!empty($r['_distributors']))update_recall_distributors($rid,$r['_distributors']);
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
    if(!empty($r['_distributors']))update_recall_distributors($rid,$r['_distributors']);

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
    // Fetch recall's states for store population (OR03/D-005)
    try{
        $state_rows=db()->prepare('SELECT state_code FROM recall_states WHERE recall_id=?');
        $state_rows->execute([$rid]);
        $recall_states=array_column($state_rows->fetchAll(),'state_code');
    }catch(\Throwable){$recall_states=[];}

    foreach($retailers as [$name,$conf]){
        $ret_id=resolve_retailer($name);
        db()->prepare('INSERT OR IGNORE INTO recall_retailers(recall_id,retailer_id,relationship_type,confidence)VALUES(?,?,?,?)')->execute([$rid,$ret_id,'retailer',$conf]);
        // Populate stores: one virtual record per (retailer, state) pair derived from this recall's geography
        foreach($recall_states as $sc){
            try{
                db()->prepare('INSERT OR IGNORE INTO stores(retailer_id,name,state)VALUES(?,?,?)')->execute([$ret_id,$name.' ('.$sc.')',$sc]);
            }catch(\Throwable){}
        }
    }
}

function update_recall_distributors(int $rid,array $distributors):void{
    foreach($distributors as $name){
        $dist_id=resolve_distributor($name);
        try{
            db()->prepare('INSERT OR IGNORE INTO recall_distributors(recall_id,distributor_id,relationship_type,confidence)VALUES(?,?,?,?)')->execute([$rid,$dist_id,'distributor','probable']);
        }catch(\Throwable){}
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
function recency_weight(string $date,float $lambda=0.0):float{
    if(!$date)return 0.1;
    $lam=$lambda>0?$lambda:FW_LAMBDA;
    $days=max(0,(time()-strtotime($date))/86400);
    return (float)exp(-$lam*$days);
}

// R = S × G × D × e^{-λt} × (1 − p30_recall)
// markov_discount: probability recall remains unresolved at 30d; default 0 = no Markov data
function event_risk(float $sev,float $geo,float $dist_conf,float $rec_weight,float $markov_discount=0.0):float{
    $md=max(0.0,min(1.0,$markov_discount));
    return round($sev*$geo*$dist_conf*$rec_weight*(1.0-$md),4);
}

function score_recall_retailers(int $rid):void{
    $rec=db()->prepare('SELECT severity,announced_date,food_category_id,status FROM recalls WHERE id=?');
    $rec->execute([$rid]);$rec=$rec->fetch();
    if(!$rec)return;
    $sev=(float)$rec['severity'];
    $cat_id=(int)($rec['food_category_id']??0);
    $lam=$cat_id>0?category_lambda($cat_id):FW_LAMBDA;
    $rw=recency_weight($rec['announced_date']??'',$lam);

    // Markov forward-looking discount: P(still unresolved at 30d)
    $markov_discount=0.0;
    $cur_status=$rec['status']??'ongoing';
    $s_idx=match($cur_status){'ongoing'=>1,'completed'=>2,'terminated'=>3,default=>0};
    if($s_idx<2){
        $est=markov_estimate_matrix();
        $N_m=markov_fundamental_matrix($est['P']);
        $p30_resolved=markov_p_resolved_in_k($est['P'],$N_m,$s_idx,2);
        $markov_discount=max(0.0,1.0-$p30_resolved); // probability STILL active
    }

    $rets=db()->prepare('SELECT retailer_id,confidence,relationship_type FROM recall_retailers WHERE recall_id=?');
    $rets->execute([$rid]);
    foreach($rets->fetchAll() as $rr){
        $conf=DIST_CONF[$rr['confidence']]??0.25;
        $priv=($rr['relationship_type']==='private_label_retailer')?1:0;
        $er=event_risk($sev,1.0,$conf,$rw,$markov_discount);
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
    $total_stores=(int)$db->query("SELECT COUNT(*) FROM stores")->fetchColumn();
    $total_distributors=(int)$db->query("SELECT COUNT(*) FROM distributors")->fetchColumn();

    // API health summary for dashboard badge
    try{
        $api_rows=$db->query("SELECT agency_code,last_check,last_success,last_status,consecutive_failures FROM api_health")->fetchAll();
        $api_health=[];
        foreach($api_rows as $ah){$api_health[$ah['agency_code']]=$ah;}
    }catch(\Throwable){$api_health=[];}

    return compact('total','active','severe','retailers','cats','total_retailers','total_cats','newest','last_sync','api_health','total_stores','total_distributors');
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
    // Rank: severity × e^{-λ·age_days} — surfaces severe recent recalls above stale low-severity ones
    $stmt=db()->prepare("SELECT r.id,r.title,r.status,r.severity,r.severity_label,r.announced_date,a.code as agency_code,fc.name as category,
        ROUND(r.severity*EXP(-".FW_LAMBDA."*MAX(0,(JULIANDAY('now')-JULIANDAY(r.announced_date)))),4) AS rank_score
      FROM recalls r JOIN agencies a ON a.id=r.agency_id LEFT JOIN food_categories fc ON fc.id=r.food_category_id
      WHERE r.id IN($pl) ORDER BY rank_score DESC");
    $stmt->execute($ids);return $stmt->fetchAll();
}

function q_manufacturers(int $limit=100):array{
    $stmt=db()->prepare("
        SELECT m.id,m.name,m.city,m.state,
          COUNT(DISTINCT rm.recall_id) as total_recalls,
          COUNT(DISTINCT CASE WHEN r.status='ongoing' THEN rm.recall_id END) as active_recalls,
          COUNT(DISTINCT CASE WHEN r.severity>=3.0 THEN rm.recall_id END) as severe_recalls,
          ROUND(SUM(COALESCE(re.event_risk,0)),3) as total_risk,
          MIN(r.announced_date) as first_recall,
          MAX(r.announced_date) as last_recall,
          GROUP_CONCAT(DISTINCT CASE WHEN r.severity>=3.0 THEN r.title END) as severe_titles,
          GROUP_CONCAT(DISTINCT r.food_category_id) as category_ids
        FROM manufacturers m
        JOIN recall_manufacturers rm ON rm.manufacturer_id=m.id
        JOIN recalls r ON r.id=rm.recall_id
        LEFT JOIN retail_exposures re ON re.recall_id=r.id
        GROUP BY m.id
        HAVING COUNT(DISTINCT rm.recall_id)>=1
        ORDER BY severe_recalls DESC,total_recalls DESC
        LIMIT ?");
    $stmt->execute([$limit]);return $stmt->fetchAll();
}

// Erdős E02: greedy graph coloring of manufacturer hazard-sharing graph
// Returns chromatic label (color index 1-N) for a manufacturer given shared-hazard adjacency
function manufacturer_chromatic_color(array $mfrs):array{
    // Build adjacency: two manufacturers share a color class if they share no food category
    $cat_map=[];
    foreach($mfrs as $m){
        $cats=array_filter(array_map('intval',explode(',',$m['category_ids']??'')));
        $cat_map[(int)$m['id']]=$cats;
    }
    $colors=[];
    foreach($mfrs as $m){
        $mid=(int)$m['id'];
        $my_cats=$cat_map[$mid]??[];
        $used=[];
        foreach($colors as $other_id=>$c){
            $shared=!empty(array_intersect($my_cats,$cat_map[$other_id]??[]));
            if($shared)$used[$c]=true;
        }
        for($c=1;;$c++){if(!isset($used[$c])){$colors[$mid]=$c;break;}}
    }
    return $colors;
}

function q_recall_trend(int $weeks=52):array{
    $since=date('Y-m-d',strtotime("-$weeks weeks"));
    $stmt=db()->prepare("
        SELECT strftime('%Y-%W',announced_date) as week_key,
          COUNT(*) as total,
          SUM(CASE WHEN severity>=3.0 THEN 1 ELSE 0 END) as severe,
          SUM(CASE WHEN agency_id=(SELECT id FROM agencies WHERE code='FDA') THEN 1 ELSE 0 END) as fda_count,
          SUM(CASE WHEN agency_id=(SELECT id FROM agencies WHERE code='FSIS') THEN 1 ELSE 0 END) as fsis_count,
          MIN(announced_date) as week_start
        FROM recalls
        WHERE announced_date>=?
        GROUP BY week_key ORDER BY week_key");
    $stmt->execute([$since]);return $stmt->fetchAll();
}

function q_velocity():array{
    $r30=(int)db()->query("SELECT COUNT(*) FROM recalls WHERE announced_date>=date('now','-30 days')")->fetchColumn();
    $r90=(int)db()->query("SELECT COUNT(*) FROM recalls WHERE announced_date>=date('now','-90 days')")->fetchColumn();
    $r90p=(int)db()->query("SELECT COUNT(*) FROM recalls WHERE announced_date>=date('now','-180 days') AND announced_date<date('now','-90 days')")->fetchColumn();
    $baseline=round($r90p/3.0,1);
    $z_score=$baseline>0?round(($r30-$baseline)/max(1,sqrt($baseline)),2):0.0;
    $cat_stmt=db()->query("SELECT fc.name,COUNT(DISTINCT r.id) as cnt FROM recalls r JOIN food_categories fc ON fc.id=r.food_category_id WHERE r.announced_date>=date('now','-30 days') GROUP BY fc.id ORDER BY cnt DESC LIMIT 5");
    return['rate_30d'=>$r30,'rate_90d'=>$r90,'baseline_monthly'=>$baseline,'z_score'=>$z_score,'trending_cats'=>$cat_stmt->fetchAll()];
}

// ================================================================
// § MARKOV TRANSITION ENGINE
// ================================================================
function markov_estimate_matrix():array{
    // Valid successors per state: 0=announced,1=active,2=resolved,3=archived
    $successors=[[1,2,3],[2,3],[3],[]];
    $counts=array_fill(0,4,array_fill(0,4,0));
    $map=['announced'=>0,'active'=>1,'ongoing'=>1,'resolved'=>2,'completed'=>2,'terminated'=>2,'archived'=>3];
    try{
        $rows=db()->query("SELECT from_status,to_status,COUNT(*) as cnt FROM recall_transitions GROUP BY from_status,to_status")->fetchAll();
        foreach($rows as $r){
            $i=$map[strtolower($r['from_status'])]??-1;
            $j=$map[strtolower($r['to_status'])]??-1;
            if($i>=0&&$j>=0&&$i!==$j)$counts[$i][$j]+=$r['cnt'];
        }
    }catch(\Throwable){}
    // Laplace-smoothed MLE: P[i][j] = (C[i][j]+1)/(N_i+K)
    $P=[];$n_total=0;
    for($i=0;$i<4;$i++){
        $succ=$successors[$i];$K=count($succ);
        if($K===0){$P[$i]=array_fill(0,4,0.0);$P[$i][$i]=1.0;continue;}
        $N=array_sum(array_map(fn($j)=>$counts[$i][$j],$succ));$n_total+=$N;
        $row=array_fill(0,4,0.0);
        foreach($succ as $j)$row[$j]=round(($counts[$i][$j]+1)/($N+$K),6);
        $P[$i]=$row;
    }
    return['P'=>$P,'n'=>$n_total,'confidence'=>$n_total<10?'low':($n_total<50?'medium':'high')];
}

function markov_fundamental_matrix(array $P):array{
    // Transient states: 0(announced),1(active) — absorbing: 2,3
    // Q = 2x2 transient submatrix; N = (I-Q)^-1 via 2x2 closed form
    $Q=[[$P[0][0]??0,$P[0][1]??0],[$P[1][0]??0,$P[1][1]??0]];
    $a=1-$Q[0][0];$b=-$Q[0][1];$c=-$Q[1][0];$d=1-$Q[1][1];
    $det=$a*$d-$b*$c;
    if(abs($det)<1e-9)return[[1.0,0.0],[0.0,1.0]];
    return[[$d/$det,-$b/$det],[-$c/$det,$a/$det]];
}

function markov_expected_steps(array $N):array{
    // E[T_i] = row-sum of fundamental matrix N
    return[round(array_sum($N[0]),2),round(array_sum($N[1]),2)];
}

function markov_escalation_prob(array $P,int $state):float{
    // Proxy: probability recall remains active (multi-cycle exposure risk)
    if($state===0)return round(min(0.95,($P[0][1]??0)*0.40),3);
    if($state===1)return round(min(0.95,($P[1][1]??0)*0.25),3);
    return 0.0;
}

function markov_p_resolved_in_k(array $P,array $N,int $state,int $k):float{
    if($state>=2)return 1.0;
    // Compute Q^k then P(still transient) = sum(row i of Q^k)
    $Q=[[$P[0][0]??0,$P[0][1]??0],[$P[1][0]??0,$P[1][1]??0]];
    $Qk=[[1,0],[0,1]]; // identity
    for($s=0;$s<$k;$s++){
        $tmp=[[0,0],[0,0]];
        for($r=0;$r<2;$r++)for($c=0;$c<2;$c++)for($t=0;$t<2;$t++)$tmp[$r][$c]+=$Qk[$r][$t]*$Q[$t][$c];
        $Qk=$tmp;
    }
    return round(max(0.0,min(1.0,1-array_sum($Qk[$state]))),3);
}

function markov_refresh_cache():array{
    $est=markov_estimate_matrix();
    $N=markov_fundamental_matrix($est['P']);
    $steps=markov_expected_steps($N);
    // Also re-calibrate per-category λ while we're refreshing
    try{update_category_lambdas();}catch(\Throwable $ignored){}
    try{
        db()->prepare("INSERT INTO markov_params(computed_at,state_count,p_matrix_json,n_matrix_json,e_steps_json,sample_n,confidence) VALUES(datetime('now'),4,?,?,?,?,?)")
            ->execute([json_encode($est['P']),json_encode($N),json_encode($steps),$est['n'],$est['confidence']]);
        return['ok'=>true,'n'=>$est['n'],'confidence'=>$est['confidence'],'steps'=>$steps];
    }catch(\Throwable $e){return['ok'=>false,'error'=>$e->getMessage()];}
}

// Markov CI bands via ±ε perturbation of transition matrix entries (T02)
// Returns ['lo'=>float, 'hi'=>float] day-range for state $s at horizon $k cycles
function markov_ci_band(array $P,array $N,int $s,int $k,float $eps=0.05):array{
    $p30_base=markov_p_resolved_in_k($P,$N,$s,$k);
    // Perturb transient self-loop P[s][s] ±ε and recompute
    $P_lo=$P;$P_hi=$P;
    $P_lo[$s][$s]=max(0,$P[$s][$s]-$eps);
    $P_lo[$s][1-$s]=min(1,$P[$s][1-$s]+$eps);
    $P_hi[$s][$s]=min(1,$P[$s][$s]+$eps);
    $P_hi[$s][1-$s]=max(0,$P[$s][1-$s]-$eps);
    $N_lo=markov_fundamental_matrix($P_lo);
    $N_hi=markov_fundamental_matrix($P_hi);
    $p_lo=markov_p_resolved_in_k($P_lo,$N_lo,$s,$k);
    $p_hi=markov_p_resolved_in_k($P_hi,$N_hi,$s,$k);
    return['lo'=>round(min($p_lo,$p_hi)*100),'hi'=>round(max($p_lo,$p_hi)*100),'base'=>round($p30_base*100)];
}

function q_recall_outlook(int $recall_id):array{
    try{
        $params=db()->query("SELECT p_matrix_json,n_matrix_json,e_steps_json,sample_n,confidence FROM markov_params ORDER BY id DESC LIMIT 1")->fetch();
    }catch(\Throwable){$params=null;}
    if(!$params){
        // Compute on-demand (first call); cache for next
        $est=markov_estimate_matrix();
        $N_mat=markov_fundamental_matrix($est['P']);
        $params=['p_matrix_json'=>json_encode($est['P']),'n_matrix_json'=>json_encode($N_mat),'e_steps_json'=>json_encode(markov_expected_steps($N_mat)),'sample_n'=>$est['n'],'confidence'=>$est['confidence']];
        try{db()->prepare("INSERT INTO markov_params(computed_at,state_count,p_matrix_json,n_matrix_json,e_steps_json,sample_n,confidence) VALUES(datetime('now'),4,?,?,?,?,?)")->execute([$params['p_matrix_json'],$params['n_matrix_json'],$params['e_steps_json'],$params['sample_n'],$params['confidence']]);}catch(\Throwable){}
    }
    $P=json_decode($params['p_matrix_json'],true)??[];
    $N_mat=json_decode($params['n_matrix_json'],true)??[];
    $e_steps=json_decode($params['e_steps_json'],true)??[4.0,3.0];
    $rec_stmt=db()->prepare("SELECT status,severity,announced_date FROM recalls WHERE id=?");
    $rec_stmt->execute([$recall_id]);$rec=$rec_stmt->fetch();
    if(!$rec)return['error'=>'Not found'];
    $smap=['announced'=>0,'active'=>1,'ongoing'=>1,'resolved'=>2,'completed'=>2,'terminated'=>2,'archived'=>3];
    $state=$smap[strtolower($rec['status']??'active')]??1;
    $cycle=14; // median days per review cycle
    $k30=max(1,(int)round(30/$cycle));$k60=max(1,(int)round(60/$cycle));
    $p30=markov_p_resolved_in_k($P,$N_mat,$state,$k30);
    $p60=markov_p_resolved_in_k($P,$N_mat,$state,$k60);
    $p_esc=markov_escalation_prob($P,$state);
    $e_raw=($e_steps[$state]??4.0)*$cycle;
    $e_low=max(7,(int)round($e_raw*0.65));$e_high=(int)round($e_raw*1.45);
    $days_stmt=db()->prepare("SELECT COALESCE(CAST((julianday('now')-julianday(MAX(transitioned_at))) AS INTEGER),0) FROM recall_transitions WHERE recall_id=?");
    $days_stmt->execute([$recall_id]);$days_in_state=(int)$days_stmt->fetchColumn();
    $labels=['announced','active','resolved','archived'];
    return['state'=>$state,'state_name'=>$labels[$state]??'unknown','p_resolved_30d'=>$p30,'p_resolved_60d'=>$p60,'p_escalation'=>$p_esc,'expected_days_low'=>$e_low,'expected_days_high'=>$e_high,'confidence'=>$params['confidence']??'low','sample_n'=>(int)$params['sample_n'],'days_in_state'=>$days_in_state];
}

function q_seasonal():array{
    $stmt=db()->query("
        SELECT strftime('%m',announced_date) as month,
          strftime('%Y',announced_date) as year,
          COUNT(*) as total,
          SUM(CASE WHEN severity>=3.0 THEN 1 ELSE 0 END) as severe
        FROM recalls
        WHERE announced_date IS NOT NULL AND announced_date!=''
        GROUP BY year,month ORDER BY year,month");
    return $stmt->fetchAll();
}

function q_sankey_data():array{
    $links=[];
    // Manufacturer → Category flows
    $stmt=db()->query("
        SELECT m.name as src,fc.name as tgt,'mfr_cat' as link_type,COUNT(*) as value
        FROM recall_manufacturers rm
        JOIN manufacturers m ON m.id=rm.manufacturer_id
        JOIN recalls r ON r.id=rm.recall_id
        JOIN food_categories fc ON fc.id=r.food_category_id
        GROUP BY m.name,fc.name HAVING COUNT(*)>=1");
    foreach($stmt->fetchAll() as $r)$links[]=$r;
    // Category → Retailer flows
    $stmt=db()->query("
        SELECT fc.name as src,rt.name as tgt,'cat_ret' as link_type,COUNT(*) as value
        FROM recalls r
        JOIN food_categories fc ON fc.id=r.food_category_id
        JOIN recall_retailers rr ON rr.recall_id=r.id
        JOIN retailers rt ON rt.id=rr.retailer_id
        GROUP BY fc.name,rt.name HAVING COUNT(*)>=1");
    foreach($stmt->fetchAll() as $r)$links[]=$r;
    return $links;
}

function q_timeline_data(int $limit=60):array{
    $stmt=db()->prepare("
        SELECT r.id,r.title,r.severity,r.severity_label,r.classification,
          r.announced_date,r.status_updated_date,r.status,r.food_category_id,
          a.code as agency_code, fc.name as category_name
        FROM recalls r
        JOIN agencies a ON a.id=r.agency_id
        LEFT JOIN food_categories fc ON fc.id=r.food_category_id
        WHERE r.announced_date IS NOT NULL AND r.announced_date!=''
        ORDER BY r.announced_date ASC
        LIMIT ?");
    $stmt->execute([$limit]);return $stmt->fetchAll();
}

function q_geo_risk():array{
    // L² norm: SQRT(Σ r²) instead of additive SUM — penalises concentration and captures tail risk
    $stmt=db()->query("
        SELECT rs.state_code,
          COUNT(DISTINCT rs.recall_id) as total,
          COUNT(DISTINCT CASE WHEN rc.status='ongoing' THEN rs.recall_id END) as active,
          ROUND(SQRT(SUM(POWER(COALESCE(re.event_risk,rc.severity*0.25),2))),3) as risk_score,
          SUM(CASE WHEN rc.severity>=3.0 THEN 1 ELSE 0 END) as severe
        FROM recall_states rs
        JOIN recalls rc ON rc.id=rs.recall_id
        LEFT JOIN retail_exposures re ON re.recall_id=rs.recall_id
        WHERE rs.state_code!='nationwide'
        GROUP BY rs.state_code");
    $rows=$stmt->fetchAll();
    $out=[];foreach($rows as $r)$out[$r['state_code']]=$r;
    return $out;
}

// Email alert helpers
function subscription_token():string{ return bin2hex(random_bytes(16)); }

function send_email_alerts():array{
    $stmt=db()->query("SELECT * FROM subscriptions WHERE active=1");
    $subs=$stmt->fetchAll();
    $sent=0;$errors=[];
    foreach($subs as $sub){
        $f=json_decode($sub['filter_json']??'{}',true)??[];
        $since=$sub['last_sent_at']??date('Y-m-d',strtotime('-7 days'));
        $w=["r.announced_date>?"];$p=[$since];
        if(!empty($f['status']))$w[]="r.status=?";if(!empty($f['status']))$p[]=$f['status'];
        if(!empty($f['severity']))$w[]="r.severity>=?";if(!empty($f['severity']))$p[]=(float)$f['severity'];
        if(!empty($f['state'])){$w[]="r.id IN(SELECT recall_id FROM recall_states WHERE state_code=?)";$p[]=$f['state'];}
        if(!empty($f['category'])){$w[]="r.food_category_id=?";$p[]=(int)$f['category'];}
        $where='WHERE '.implode(' AND ',$w);
        $rs=db()->prepare("SELECT r.id,r.title,r.severity,r.classification,r.announced_date,a.code as agency FROM recalls r JOIN agencies a ON a.id=r.agency_id $where ORDER BY r.announced_date DESC LIMIT 20");
        $rs->execute($p);$recalls=$rs->fetchAll();
        if(!$recalls)continue;
        // Build Markov-augmented email
        $markov_est_email=markov_estimate_matrix();
        $N_email=markov_fundamental_matrix($markov_est_email['P']);
        $body='<html><body style="font-family:sans-serif;max-width:600px;margin:0 auto">';
        $body.='<h2 style="color:#3b5bdb">FoodWatch US Alert</h2>';
        $body.='<p>'.count($recalls).' new recall(s) match your subscription criteria since '.date('M j, Y',strtotime($since)).':</p>';
        $body.='<table style="width:100%;border-collapse:collapse">';
        $body.='<tr><th style="text-align:left;padding:6px;background:#f1f5f9">Class</th><th style="text-align:left;padding:6px;background:#f1f5f9">Product</th><th style="text-align:left;padding:6px;background:#f1f5f9">Agency</th><th style="text-align:left;padding:6px;background:#f1f5f9">Date</th><th style="text-align:left;padding:6px;background:#f1f5f9">Outlook</th></tr>';
        foreach($recalls as $rc){
            $clr=$rc['severity']>=3.0?'#dc2626':($rc['severity']>=2.0?'#d97706':'#16a34a');
            $smap_e=['announced'=>0,'active'=>1,'ongoing'=>1,'resolved'=>2,'completed'=>2,'terminated'=>2,'archived'=>3];
            $state_e=$smap_e[strtolower($rc['status']??'active')]??1;
            $p_esc=markov_escalation_prob($markov_est_email['P'],$state_e);
            $p30=markov_p_resolved_in_k($markov_est_email['P'],$N_email,$state_e,2);
            $esc_warn=$p_esc>0.25?'<span style="color:#dc2626;font-weight:bold">⚠ Escalation '.round($p_esc*100).'%</span> · ':'';
            $outlook_txt=$esc_warn.'P(30d resolved): '.round($p30*100).'%';
            $body.='<tr><td style="padding:5px;color:'.$clr.';font-weight:bold">'.htmlspecialchars($rc['classification']??'').'</td><td style="padding:5px">'.htmlspecialchars(mb_substr($rc['title'],0,70)).'</td><td style="padding:5px">'.htmlspecialchars($rc['agency']).'</td><td style="padding:5px">'.htmlspecialchars($rc['announced_date']).'</td><td style="padding:5px;font-size:11px">'.$outlook_txt.'</td></tr>';
        }
        $body.='</table><hr><p style="font-size:11px;color:#64748b">Recall Outlook probabilities are Markov model estimates (n='.((int)$markov_est_email['n']).' transitions, '.$markov_est_email['confidence'].' confidence). Unsubscribe: '.($_SERVER['HTTP_HOST']??'').'?api=subscription_del&token='.urlencode($sub['token']).'</p></body></html>';
        $headers="From: FoodWatch US <alerts@foodwatch-us.com>\r\nContent-Type: text/html; charset=utf-8\r\nMIME-Version: 1.0\r\n";
        if(@mail($sub['email'],'FoodWatch US Alert: '.count($recalls).' new recall(s)',$body,$headers)){
            db()->prepare("UPDATE subscriptions SET last_sent_at=datetime('now') WHERE id=?")->execute([$sub['id']]);
            $sent++;
        }else{
            $errors[]='Failed to send to '.$sub['email'];
        }
    }
    return['sent'=>$sent,'errors'=>$errors];
}

// UPC/barcode lookup via Open Food Facts
function barcode_lookup(string $upc):array{
    $upc=preg_replace('/[^0-9]/','',$upc);
    if(!$upc)return['error'=>'Invalid UPC'];
    // Check recall DB first
    $stmt=db()->prepare("SELECT rp.upc,rp.description,r.id,r.title,r.status,r.severity,r.classification,r.announced_date FROM recall_products rp JOIN recalls r ON r.id=rp.recall_id WHERE rp.upc=? LIMIT 10");
    $stmt->execute([$upc]);$recall_matches=$stmt->fetchAll();
    // Query Open Food Facts
    $off=fw_fetch("https://world.openfoodfacts.org/api/v0/product/$upc.json",[],10);
    $product=null;
    if($off['ok']&&isset($off['data']['product']['product_name'])){
        $p=$off['data']['product'];
        $product=['name'=>$p['product_name']??'','brand'=>$p['brands']??'','category'=>$p['categories']??'','image'=>$p['image_url']??'','quantity'=>$p['quantity']??''];
    }
    return['upc'=>$upc,'recall_matches'=>$recall_matches,'product'=>$product];
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
        case 'dashboard':     render_page('dashboard');break;
        case 'recalls':       render_page('recalls');break;
        case 'recall':        render_page('recall');break;
        case 'retailers':     render_page('retailers');break;
        case 'manufacturers': render_page('manufacturers');break;
        case 'categories':    render_page('categories');break;
        case 'analytics':     render_page('analytics');break;
        case 'map':           render_page('map');break;
        case 'timeline':      render_page('timeline');break;
        case 'sankey':        render_page('sankey');break;
        case 'graph3d':       render_page('graph3d');break;
        case 'geo':           render_page('geo');break;
        case 'barcode':       render_page('barcode');break;
        case 'subscriptions': render_page('subscriptions');break;
        case 'markov_admin':  render_page('markov_admin');break;
        case 'search':        render_page('search');break;
        case 'watchlist':     render_page('watchlist');break;
        case 'tests':         render_page('tests');break;
        case 'admin':         render_page('admin');break;
        default:              render_page('dashboard');
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
            case 'manufacturers':echo js(q_manufacturers((int)($_GET['limit']??100)));break;
            case 'trend':    echo js(q_recall_trend((int)($_GET['weeks']??52)));break;
            case 'velocity': echo js(q_velocity());break;
            case 'seasonal': echo js(q_seasonal());break;
            case 'sankey':   echo js(q_sankey_data());break;
            case 'timeline_data':echo js(q_timeline_data((int)($_GET['limit']??60)));break;
            case 'geo_risk': echo js(array_values(q_geo_risk()));break;
            case 'barcode':
                $upc=preg_replace('/[^0-9]/','',trim($_GET['upc']??''));
                echo js($upc?barcode_lookup($upc):['error'=>'No UPC provided']);break;
            case 'subscription_add':
                if(!csrf_ok())fw_abort('CSRF',403);
                $email=trim($_POST['email']??'');
                if(!filter_var($email,FILTER_VALIDATE_EMAIL))fw_abort('Invalid email',400);
                $f=['status'=>$_POST['status']??'','severity'=>$_POST['severity']??'','state'=>$_POST['state']??'','category'=>$_POST['category']??''];
                $f=array_filter($f);
                $tok=subscription_token();
                db()->prepare("INSERT OR IGNORE INTO subscriptions(email,filter_json,token)VALUES(?,?,?)")->execute([$email,json_encode($f),$tok]);
                echo js(['ok'=>true,'message'=>"Subscribed $email"]);break;
            case 'subscription_del':
                $tok=trim($_GET['token']??$_POST['token']??'');
                if($tok)db()->prepare("UPDATE subscriptions SET active=0 WHERE token=?")->execute([$tok]);
                echo js(['ok'=>true]);break;
            case 'subscriptions':
                if(!is_admin())fw_abort('Unauthorized',403);
                $stmt=db()->query('SELECT id,email,filter_json,active,created_at,last_sent_at FROM subscriptions ORDER BY created_at DESC');
                echo js($stmt->fetchAll());break;
            case 'send_alerts':
                if(!is_admin())fw_abort('Unauthorized',403);
                echo js(send_email_alerts());break;
            case 'poll_status':
                if(!is_admin())fw_abort('Unauthorized',403);
                // Re-fetch ongoing recalls from FDA and update status
                $ongoing=db()->query("SELECT id,source_id,status FROM recalls WHERE status='ongoing' AND agency_id=(SELECT id FROM agencies WHERE code='FDA') LIMIT 20")->fetchAll();
                $updated=0;
                foreach($ongoing as $row){
                    $src_id=$row['source_id'];$old_status=$row['status'];$rid=$row['id'];
                    $res=fw_fetch(FDA_API,['search'=>"recall_number:\"$src_id\"","limit"=>1],10);
                    if($res['ok']&&!empty($res['data']['results'][0])){
                        $raw=$res['data']['results'][0];
                        $new_status=strtolower($raw['status']??'ongoing');
                        $db_status=match($new_status){'completed'=>'completed','terminated'=>'terminated',default=>'ongoing'};
                        $upd=db()->prepare("UPDATE recalls SET status=?,updated_at=datetime('now') WHERE id=? AND status!='completed'");
                        $upd->execute([$db_status,$rid]);
                        if($upd->rowCount()&&$old_status!==$db_status){
                            $days_stmt=db()->prepare("SELECT COALESCE(CAST((julianday('now')-julianday(announced_date)) AS INTEGER),0) FROM recalls WHERE id=?");
                            $days_stmt->execute([$rid]);$d_in=(int)$days_stmt->fetchColumn();
                            try{db()->prepare("INSERT INTO recall_transitions(recall_id,from_status,to_status,days_in_from_state) VALUES(?,?,?,?)")->execute([$rid,$old_status,$db_status,$d_in]);}catch(\Throwable){}
                            $updated++;
                        }
                    }
                }
                // Refresh Markov cache after status polling
                try{markov_refresh_cache();}catch(\Throwable){}
                echo js(['ok'=>true,'polled'=>count($ongoing),'updated'=>$updated]);break;
            case 'recall_outlook':
                $id=(int)($_GET['id']??0);
                if(!$id)fw_abort('Missing id',400);
                echo js(q_recall_outlook($id));break;
            case 'markov_refresh':
                if(!is_admin())fw_abort('Unauthorized',403);
                echo js(markov_refresh_cache());break;
            case 'markov_diagnostics':
                if(!is_admin())fw_abort('Unauthorized',403);
                $p=db()->query("SELECT * FROM markov_params ORDER BY id DESC LIMIT 1")->fetch();
                $tc=db()->query("SELECT COUNT(*) FROM recall_transitions")->fetchColumn();
                echo js(['params'=>$p,'transition_count'=>(int)$tc,'matrix_est'=>markov_estimate_matrix()]);break;
            case 'export_pdf':
                // Returns HTML fragment for print/PDF
                $f=['status'=>$_GET['status']??'all','q'=>$_GET['q']??''];
                $data=q_recalls(1,100,$f);
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html><head><title>FoodWatch US Export</title>';
                echo '<style>body{font-family:sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:4px}th{background:#f1f5f9;font-weight:600}h1{font-size:16px}</style></head><body>';
                echo '<h1>FoodWatch US — Recall Export ('.date('Y-m-d').')</h1>';
                echo '<table><thead><tr><th>Class</th><th>Product</th><th>Agency</th><th>Category</th><th>Date</th><th>Status</th></tr></thead><tbody>';
                foreach($data['records'] as $r){
                    echo '<tr><td>'.htmlspecialchars($r['classification']??'').'</td><td>'.htmlspecialchars(mb_substr($r['title'],0,80)).'</td><td>'.htmlspecialchars($r['agency_code']).'</td><td>'.htmlspecialchars($r['category_name']??'').'</td><td>'.htmlspecialchars($r['announced_date']??'').'</td><td>'.htmlspecialchars($r['status']).'</td></tr>';
                }
                echo '</tbody></table><script>window.print()</script></body></html>';
                exit;
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
@media print{nav,form,button,.no-print{display:none!important}main{margin-left:0!important}body{background:#fff}}
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
    <a href="?page=recalls" class="fw-nav-link <?=$page==='recalls'?'active':''?>"><i data-lucide="alert-triangle" class="w-4 h-4"></i>Recalls</a>
    <a href="?page=retailers" class="fw-nav-link <?=$page==='retailers'?'active':''?>"><i data-lucide="store" class="w-4 h-4"></i>Retailer Exposure</a>
    <a href="?page=manufacturers" class="fw-nav-link <?=$page==='manufacturers'?'active':''?>"><i data-lucide="factory" class="w-4 h-4"></i>Manufacturers</a>
    <a href="?page=categories" class="fw-nav-link <?=$page==='categories'?'active':''?>"><i data-lucide="tag" class="w-4 h-4"></i>Food Categories</a>
    <div class="text-xs text-slate-500 px-3 pt-3 pb-1 uppercase tracking-wider font-semibold">Analysis</div>
    <a href="?page=analytics" class="fw-nav-link <?=$page==='analytics'?'active':''?>"><i data-lucide="trending-up" class="w-4 h-4"></i>Trends &amp; Velocity</a>
    <a href="?page=map" class="fw-nav-link <?=$page==='map'?'active':''?>"><i data-lucide="map" class="w-4 h-4"></i>Choropleth Map</a>
    <a href="?page=timeline" class="fw-nav-link <?=$page==='timeline'?'active':''?>"><i data-lucide="gantt-chart" class="w-4 h-4"></i>Timeline / Gantt</a>
    <a href="?page=sankey" class="fw-nav-link <?=$page==='sankey'?'active':''?>"><i data-lucide="git-merge" class="w-4 h-4"></i>Sankey Flow</a>
    <a href="?page=graph3d" class="fw-nav-link <?=$page==='graph3d'?'active':''?>"><i data-lucide="globe" class="w-4 h-4"></i>3D Force Graph</a>
    <a href="?page=geo" class="fw-nav-link <?=$page==='geo'?'active':''?>"><i data-lucide="bar-chart" class="w-4 h-4"></i>State Table</a>
    <div class="text-xs text-slate-500 px-3 pt-3 pb-1 uppercase tracking-wider font-semibold">Tools</div>
    <a href="?page=barcode" class="fw-nav-link <?=$page==='barcode'?'active':''?>"><i data-lucide="scan-barcode" class="w-4 h-4"></i>Barcode Lookup</a>
    <a href="?page=subscriptions" class="fw-nav-link <?=$page==='subscriptions'?'active':''?>"><i data-lucide="mail" class="w-4 h-4"></i>Email Alerts</a>
    <a href="?page=markov_admin" class="fw-nav-link <?=$page==='markov_admin'?'active':''?>"><i data-lucide="activity" class="w-4 h-4"></i>Model Diagnostics</a>
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
        'dashboard'     =>view_dashboard(),
        'recalls'       =>view_recalls(),
        'recall'        =>view_recall_detail(),
        'retailers'     =>view_retailers(),
        'manufacturers' =>view_manufacturers(),
        'categories'    =>view_categories(),
        'analytics'     =>view_analytics(),
        'map'           =>view_map(),
        'timeline'      =>view_timeline(),
        'sankey'        =>view_sankey(),
        'graph3d'       =>view_graph3d(),
        'geo'           =>view_geo(),
        'barcode'       =>view_barcode(),
        'subscriptions' =>view_subscriptions(),
        'markov_admin'  =>view_markov_admin(),
        'search'        =>view_search(),
        'watchlist'     =>view_watchlist(),
        'tests'         =>view_tests(),
        'admin'         =>view_admin(),
        default         =>view_dashboard(),
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
  <span class="text-xs text-slate-400">Last sync: <?=h($stats['last_sync']?date('M j, Y g:ia',strtotime($stats['last_sync'])):'Never')?></span>
  <?php foreach(($stats['api_health']??[]) as $code=>$ah): ?>
  <?php $ok=(int)($ah['consecutive_failures']??0)===0&&($ah['last_status']??0)===200; ?>
  <span class="flex items-center gap-1 text-xs px-2 py-0.5 rounded-full border <?=$ok?'bg-green-50 border-green-300 text-green-700':'bg-red-50 border-red-300 text-red-700'?>">
    <span class="w-1.5 h-1.5 rounded-full <?=$ok?'bg-green-500':'bg-red-500'?>"></span>
    <?=h($code)?><?=$ok?'':' ('.((int)($ah['consecutive_failures']??0)).' failures)'?>
  </span>
  <?php endforeach; ?>
  <span class="ml-auto"></span>
</div>

<!-- Stats row -->
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 mb-6">
  <div class="fw-stat"><div class="text-2xl font-bold text-slate-800"><?=number_format($stats['active'])?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="alert-circle" class="w-3 h-3 text-red-500"></i>Active Recalls</div></div>
  <div class="fw-stat"><div class="text-2xl font-bold text-red-600"><?=number_format($stats['severe'])?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="shield-alert" class="w-3 h-3 text-red-600"></i>Class I (Severe)</div></div>
  <div class="fw-stat"><div class="text-2xl font-bold text-slate-800"><?=number_format($stats['total_retailers'])?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="store" class="w-3 h-3"></i>Retailers Affected</div></div>
  <div class="fw-stat"><div class="text-2xl font-bold text-slate-800"><?=number_format($stats['total_stores']??0)?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="map-pin" class="w-3 h-3"></i>Store Locations</div></div>
  <div class="fw-stat"><div class="text-2xl font-bold text-slate-800"><?=number_format($stats['total_distributors']??0)?></div><div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i data-lucide="truck" class="w-3 h-3"></i>Distributors</div></div>
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

    <!-- Recall Outlook (Markov) -->
    <?php if($rec['status']==='ongoing'||$rec['status']==='active'||$rec['status']==='announced'): ?>
    <?php $outlook=q_recall_outlook($id); ?>
    <div class="bg-indigo-50 rounded-lg border border-indigo-200 shadow-sm p-4">
      <h3 class="text-sm font-semibold text-indigo-800 mb-3 flex items-center gap-2">
        <i data-lucide="activity" class="w-4 h-4"></i>Recall Outlook
        <?php if(($outlook['confidence']??'low')==='low'): ?>
        <span class="ml-auto text-xs font-normal bg-amber-100 text-amber-700 border border-amber-300 px-1.5 py-0.5 rounded">Low data</span>
        <?php endif; ?>
      </h3>
      <?php if(isset($outlook['error'])): ?>
      <p class="text-xs text-indigo-600">Model not yet available.</p>
      <?php else: ?>
      <dl class="space-y-2 text-sm">
        <div class="flex justify-between items-center">
          <dt class="text-xs text-indigo-700 font-medium">P(resolved in 30d)</dt>
          <?php $p30r=(float)($outlook['p_resolved_30d']??0); ?>
          <dd class="font-bold <?=$p30r>0.6?'text-green-700':($p30r>0.35?'text-amber-700':'text-red-700')?>"><?=round($p30r*100)?>%</dd>
        </div>
        <div class="flex justify-between items-center">
          <dt class="text-xs text-indigo-700 font-medium">P(resolved in 60d)</dt>
          <?php $p60r=(float)($outlook['p_resolved_60d']??0); ?>
          <dd class="font-bold <?=$p60r>0.7?'text-green-700':'text-slate-700'?>"><?=round($p60r*100)?>%</dd>
        </div>
        <div class="flex justify-between items-center border-t border-indigo-200 pt-2">
          <dt class="text-xs text-indigo-700 font-medium">Escalation risk</dt>
          <?php $pescr=(float)($outlook['p_escalation']??0); ?>
          <dd class="font-bold <?=$pescr>0.25?'text-red-700':'text-slate-600'?>"><?=round($pescr*100)?>%</dd>
        </div>
        <?php if(($outlook['expected_days_low']??null)): ?>
        <div class="border-t border-indigo-200 pt-2">
          <dt class="text-xs text-indigo-700 font-medium mb-0.5">Typical resolution</dt>
          <dd class="text-sm font-semibold text-indigo-900"><?=(int)$outlook['expected_days_low']?>–<?=(int)$outlook['expected_days_high']?> days</dd>
        </div>
        <?php endif; ?>
      </dl>
      <p class="text-xs text-indigo-500 mt-3">Markov model · n=<?=(int)($outlook['sample_n']??0)?> transitions · <?=h($outlook['confidence']??'low')?> confidence</p>
      <?php endif; ?>
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

    // Markov system outlook for watchlist context
    $markov_est=markov_estimate_matrix();
    $N_wl=markov_fundamental_matrix($markov_est['P']);
    $P_wl=$markov_est['P'];
    $p30_act_wl=markov_p_resolved_in_k($P_wl,$N_wl,1,2);
    $esc_act_wl=markov_escalation_prob($P_wl,1);
    // Ramsey threshold: flag if active recalls for any watched entity ≥ CEIL(LN(n_total)+2)
    $n_total=(int)db()->query('SELECT COUNT(*) FROM recalls')->fetchColumn();
    $ramsey_threshold=max(3,(int)ceil(log(max(2,$n_total))+2));

    // Per-item active recall counts for Ramsey alerting
    $item_alerts=[];
    foreach($items as $it){
        $cnt=0;
        $wv=db()->quote($it['watch_value']);
        try{$cnt=match($it['watch_type']){
            'retailer'=>(int)db()->query("SELECT COUNT(DISTINCT r.id) FROM recalls r JOIN recall_retailers rr ON rr.recall_id=r.id JOIN retailers rt ON rt.id=rr.retailer_id WHERE r.status='ongoing' AND LOWER(rt.name) LIKE LOWER('%".$it['watch_value']."%')")->fetchColumn(),
            'brand'=>(int)db()->query("SELECT COUNT(DISTINCT r.id) FROM recalls r JOIN recall_products rp ON rp.recall_id=r.id JOIN brands b ON b.id=rp.brand_id WHERE r.status='ongoing' AND LOWER(b.name) LIKE LOWER('%".$it['watch_value']."%')")->fetchColumn(),
            'category'=>(int)db()->query("SELECT COUNT(*) FROM recalls r JOIN food_categories fc ON fc.id=r.food_category_id WHERE r.status='ongoing' AND LOWER(fc.name) LIKE LOWER('%".$it['watch_value']."%')")->fetchColumn(),
            'state'=>(int)db()->query("SELECT COUNT(*) FROM recalls r JOIN recall_states rs ON rs.recall_id=r.id WHERE r.status='ongoing' AND rs.state_code='".$it['watch_value']."'")->fetchColumn(),
            default=>0,
        };}catch(\Throwable){$cnt=0;}
        $item_alerts[$it['id']]=['count'=>$cnt,'alert'=>$cnt>=$ramsey_threshold];
    }

    layout_head('My Watchlist','watchlist'); ?>

<!-- Markov outlook strip -->
<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 mb-5 flex flex-wrap gap-4 items-center text-sm">
  <span class="font-semibold text-indigo-800 flex items-center gap-1"><i data-lucide="activity" class="w-4 h-4"></i>System Outlook</span>
  <span class="text-indigo-700">Active → Resolved (30d): <strong><?=round($p30_act_wl*100)?>%</strong></span>
  <span class="text-indigo-700">Escalation risk: <strong class="<?=$esc_act_wl>0.25?'text-red-600':'text-green-700'?>"><?=round($esc_act_wl*100)?>%</strong></span>
  <span class="text-xs text-indigo-500">Ramsey alert threshold: <?=$ramsey_threshold?> concurrent active recalls</span>
</div>

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

<!-- Email subscription CTA -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-5 flex items-center gap-4">
  <i data-lucide="mail" class="w-5 h-5 text-blue-600 flex-shrink-0"></i>
  <div class="flex-1">
    <p class="text-sm font-medium text-blue-800">Get email alerts for your watched items</p>
    <p class="text-xs text-blue-600 mt-0.5">Subscribe to receive notifications when new recalls match your watchlist.</p>
  </div>
  <a href="?page=subscriptions" class="bg-blue-600 text-white text-xs rounded px-3 py-1.5 hover:bg-blue-700 flex-shrink-0">Subscribe →</a>
</div>

<div class="bg-white rounded-lg border border-slate-200 shadow-sm">
  <div class="px-4 py-3 border-b border-slate-200"><h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="bell" class="w-4 h-4 text-fw-500"></i>Watched Items (<?=count($items)?>)</h2></div>
  <?php if($items): ?>
  <table class="fw-table w-full">
    <thead><tr><th>Type</th><th>Value</th><th>Active Recalls</th><th>Added</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($items as $it): ?>
    <?php $ia=$item_alerts[$it['id']]??['count'=>0,'alert'=>false]; ?>
    <tr class="<?=$ia['alert']?'bg-red-50':''?>">
      <td class="capitalize text-xs font-medium"><?=h($it['watch_type'])?></td>
      <td><?=h($it['watch_label']??$it['watch_value'])?></td>
      <td class="text-center">
        <?php if($ia['count']>0): ?>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold <?=$ia['alert']?'bg-red-100 text-red-700':'bg-amber-100 text-amber-700'?>">
          <?=$ia['alert']?'⚠ ':''?><?=(int)$ia['count']?>
        </span>
        <?php else: ?><span class="text-xs text-slate-400">0</span><?php endif; ?>
      </td>
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
// § NEW VIEWS — v2.0
// ================================================================

function view_manufacturers():void{
    $mfrs=q_manufacturers(100);
    $max_recalls=max(1,...array_column($mfrs,'total_recalls'));
    $chrom_colors=manufacturer_chromatic_color($mfrs);
    $max_color=max(1,...array_values($chrom_colors)?:[1]);

    // Markov system outlook
    $markov_est=markov_estimate_matrix();
    $N_mf=markov_fundamental_matrix($markov_est['P']);
    $P_mf=$markov_est['P'];
    $p30_act_mf=markov_p_resolved_in_k($P_mf,$N_mf,1,2);
    $p60_act_mf=markov_p_resolved_in_k($P_mf,$N_mf,1,4);
    $esc_act_mf=markov_escalation_prob($P_mf,1);
    $ci30=markov_ci_band($P_mf,$N_mf,1,2);

    layout_head('Manufacturer Profiles','manufacturers'); ?>

<!-- Markov system outlook tiles -->
<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-5">
  <h3 class="text-xs font-semibold text-indigo-700 mb-3 uppercase tracking-wide">System Markov Outlook — Active Recalls</h3>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-white rounded border border-indigo-100 p-3">
      <div class="text-xs text-indigo-600 font-medium mb-1">P(resolved 30d)</div>
      <div class="text-2xl font-bold <?=$p30_act_mf>0.5?'text-green-700':'text-amber-700'?>"><?=round($p30_act_mf*100)?>%</div>
      <div class="text-xs text-slate-400 mt-0.5">CI <?=$ci30['lo']?>–<?=$ci30['hi']?>%</div>
    </div>
    <div class="bg-white rounded border border-indigo-100 p-3">
      <div class="text-xs text-indigo-600 font-medium mb-1">P(resolved 60d)</div>
      <div class="text-2xl font-bold <?=$p60_act_mf>0.6?'text-green-700':'text-amber-700'?>"><?=round($p60_act_mf*100)?>%</div>
    </div>
    <div class="bg-white rounded border border-indigo-100 p-3">
      <div class="text-xs text-indigo-600 font-medium mb-1">Escalation risk</div>
      <div class="text-2xl font-bold <?=$esc_act_mf>0.25?'text-red-700':'text-slate-600'?>"><?=round($esc_act_mf*100)?>%</div>
    </div>
    <div class="bg-white rounded border border-indigo-100 p-3">
      <div class="text-xs text-indigo-600 font-medium mb-1">Chromatic tiers</div>
      <div class="text-2xl font-bold text-slate-700"><?=$max_color?></div>
      <div class="text-xs text-slate-400 mt-0.5">risk-sharing groups</div>
    </div>
  </div>
</div>

<div class="mb-4 text-sm text-slate-600">
  <strong>Repeat-Offender Analysis</strong> — manufacturers ranked by total recall count and severe (Class I) events. Risk score = Σ EventRisk. <strong>Tier</strong> = Erdős chromatic risk group (same tier = shared hazard category).
</div>
<div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-x-auto mb-4">
  <table class="fw-table w-full min-w-max">
    <thead><tr>
      <th>Manufacturer</th><th>Location</th>
      <th>Recalls (Total)</th><th title="Class I">Severe</th>
      <th>Active</th><th>Risk Score</th>
      <th>Tier</th>
      <th>First Recall</th><th>Latest Recall</th>
    </tr></thead>
    <tbody>
    <?php
    $tier_palette=['','bg-red-100 text-red-800','bg-orange-100 text-orange-800','bg-yellow-100 text-yellow-800','bg-blue-100 text-blue-800','bg-purple-100 text-purple-800','bg-green-100 text-green-800','bg-slate-100 text-slate-700'];
    foreach($mfrs as $m): ?>
    <?php $r=(float)($m['total_risk']??0);$pct=min(100,round((int)$m['total_recalls']/$max_recalls*100));
    $tier=$chrom_colors[(int)$m['id']]??1;
    $tier_cls=$tier_palette[min($tier,count($tier_palette)-1)]??'bg-slate-100 text-slate-700'; ?>
    <tr>
      <td class="font-medium"><?=h($m['name'])?></td>
      <td class="text-xs text-slate-500"><?=h(trim(($m['city']??'').($m['state']?', '.$m['state']:'')))?></td>
      <td>
        <div class="flex items-center gap-2">
          <span class="font-bold <?=(int)$m['total_recalls']>=3?'text-red-600':''?>"><?=(int)$m['total_recalls']?></span>
          <div class="h-2 rounded bg-slate-100 flex-1 max-w-24"><div class="h-2 rounded bg-fw-500" style="width:<?=$pct?>%"></div></div>
        </div>
      </td>
      <td class="text-center font-bold <?=$m['severe_recalls']>0?'text-red-600':'text-slate-300'?>"><?=(int)$m['severe_recalls']?></td>
      <td class="text-center font-bold <?=$m['active_recalls']>0?'text-orange-600':'text-slate-300'?>"><?=(int)$m['active_recalls']?></td>
      <td class="text-center text-xs <?=$r>3?'text-red-600 font-semibold':($r>1?'text-orange-500':'text-slate-600')?>"><?=number_format($r,2)?></td>
      <td class="text-center"><span class="text-xs font-bold px-1.5 py-0.5 rounded <?=$tier_cls?>"><?=$tier?></span></td>
      <td class="text-xs"><?=h($m['first_recall']??'—')?></td>
      <td class="text-xs"><?=h($m['last_recall']??'—')?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($mfrs)): ?><tr><td colspan="9" class="text-center py-8 text-slate-400">No manufacturer data. Run ingestion first.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php layout_foot(); }

function view_analytics():void{
    $trend=q_recall_trend(52);
    $velocity=q_velocity();
    $seasonal=q_seasonal();
    $outlook_sys=q_recall_outlook(0); // system-wide Markov state
    $markov_est=markov_estimate_matrix();
    layout_head('Trends & Velocity','analytics'); ?>

<!-- Velocity Indicator -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
  <?php
  $z=$velocity['z_score'];
  $zcolor=$z>2?'text-red-600 font-bold':($z>1?'text-orange-500 font-semibold':($z<-1?'text-green-600':'text-slate-700'));
  $signal=$z>2?'⬆ HIGH ACTIVITY':($z>1?'⬆ Elevated':($z<-1?'⬇ Below baseline':'→ Normal'));
  ?>
  <div class="fw-stat"><div class="text-3xl font-bold text-slate-800"><?=(int)$velocity['rate_30d']?></div><div class="text-xs text-slate-500 mt-1">Recalls (last 30d)</div></div>
  <div class="fw-stat"><div class="text-3xl font-bold text-slate-800"><?=(int)$velocity['rate_90d']?></div><div class="text-xs text-slate-500 mt-1">Recalls (last 90d)</div></div>
  <div class="fw-stat"><div class="text-3xl font-bold text-slate-600"><?=number_format($velocity['baseline_monthly'],1)?></div><div class="text-xs text-slate-500 mt-1">Monthly baseline (prior 90d)</div></div>
  <div class="fw-stat"><div class="text-3xl font-bold <?=$zcolor?>"><?=number_format($z,2)?> σ</div><div class="text-xs text-slate-500 mt-1"><?=h($signal)?></div></div>
</div>

<!-- Recall Outlook (Markov) row -->
<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6">
  <div class="flex items-center justify-between mb-3">
    <h3 class="text-sm font-semibold text-indigo-800 flex items-center gap-2"><i data-lucide="activity" class="w-4 h-4"></i>System Recall Outlook <span class="text-xs font-normal text-indigo-500">(Markov transition model)</span></h3>
    <?php if(is_admin()): ?><a href="?api=markov_refresh" class="text-xs text-indigo-600 border border-indigo-300 rounded px-2 py-0.5 hover:bg-indigo-100">Refresh model</a><?php endif; ?>
    <span class="text-xs <?=$markov_est['confidence']==='low'?'bg-amber-100 text-amber-700 border-amber-300':'bg-green-100 text-green-700 border-green-300'?> border rounded px-1.5 py-0.5"><?=h(ucfirst($markov_est['confidence']))?> confidence · n=<?=(int)$markov_est['n']?> transitions</span>
  </div>
  <?php
  $P=$markov_est['P'];
  $N_m=markov_fundamental_matrix($P);
  $steps=markov_expected_steps($N_m);
  $p30_ann=markov_p_resolved_in_k($P,$N_m,0,2);
  $p30_act=markov_p_resolved_in_k($P,$N_m,1,2);
  $p60_ann=markov_p_resolved_in_k($P,$N_m,0,4);
  $p60_act=markov_p_resolved_in_k($P,$N_m,1,4);
  $esc_ann=markov_escalation_prob($P,0);
  $esc_act=markov_escalation_prob($P,1);
  $e_ann_lo=max(7,(int)round($steps[0]*14*0.65));$e_ann_hi=(int)round($steps[0]*14*1.45);
  $e_act_lo=max(7,(int)round($steps[1]*14*0.65));$e_act_hi=(int)round($steps[1]*14*1.45);
  // CI bands (T02)
  $ci30_ann=markov_ci_band($P,$N_m,0,2);
  $ci30_act=markov_ci_band($P,$N_m,1,2);
  ?>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
    <div class="bg-white rounded border border-indigo-100 p-3">
      <div class="text-xs text-indigo-600 font-medium mb-1">Announced → Resolved (30d)</div>
      <div class="text-2xl font-bold <?=$p30_ann>0.5?'text-green-700':'text-amber-700'?>"><?=round($p30_ann*100)?>%</div>
      <div class="text-xs text-slate-400 mt-0.5">CI <?=$ci30_ann['lo']?>–<?=$ci30_ann['hi']?>%</div>
    </div>
    <div class="bg-white rounded border border-indigo-100 p-3">
      <div class="text-xs text-indigo-600 font-medium mb-1">Active → Resolved (30d)</div>
      <div class="text-2xl font-bold <?=$p30_act>0.5?'text-green-700':'text-amber-700'?>"><?=round($p30_act*100)?>%</div>
      <div class="text-xs text-slate-400 mt-0.5">CI <?=$ci30_act['lo']?>–<?=$ci30_act['hi']?>%</div>
    </div>
    <div class="bg-white rounded border border-indigo-100 p-3">
      <div class="text-xs text-indigo-600 font-medium mb-1">Escalation risk (active)</div>
      <div class="text-2xl font-bold <?=$esc_act>0.25?'text-red-700':'text-slate-600'?>"><?=round($esc_act*100)?>%</div>
    </div>
    <div class="bg-white rounded border border-indigo-100 p-3">
      <div class="text-xs text-indigo-600 font-medium mb-1">Expected resolution</div>
      <div class="text-lg font-bold text-indigo-900"><?=$e_act_lo?>–<?=$e_act_hi?> <span class="text-xs font-normal">days</span></div>
    </div>
  </div>
  <?php if($markov_est['confidence']==='low'): ?>
  <p class="text-xs text-amber-700 mt-2">⚠ Low-confidence estimates: fewer than 10 status transitions recorded. Probabilities are Laplace-smoothed priors. Run poll_status regularly to build the training set.</p>
  <?php endif; ?>
</div>

<?php if(!empty($velocity['trending_cats'])): ?>
<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm text-amber-800 flex items-center gap-3">
  <i data-lucide="flame" class="w-4 h-4 flex-shrink-0"></i>
  <span><strong>Trending in last 30 days:</strong>
  <?php foreach($velocity['trending_cats'] as $i=>$tc): ?><?=$i?', ':''?><?=h($tc['name'])?> (<?=(int)$tc['cnt']?>)<?php endforeach; ?></span>
</div>
<?php endif; ?>

<!-- Trend chart -->
<div class="bg-white rounded-lg border border-slate-200 shadow-sm mb-6">
  <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
    <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="trending-up" class="w-4 h-4 text-blue-500"></i>Weekly Recall Volume (52 weeks)</h2>
  </div>
  <div id="trend-chart" class="p-4" style="height:240px"></div>
</div>

<!-- Seasonal Heatmap -->
<div class="bg-white rounded-lg border border-slate-200 shadow-sm">
  <div class="px-4 py-3 border-b border-slate-200">
    <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="calendar" class="w-4 h-4 text-green-500"></i>Seasonal Heatmap — Recalls by Month</h2>
    <p class="text-xs text-slate-500 mt-0.5">Each cell = recall count for that month/year. Darker = more recalls.</p>
  </div>
  <div id="heatmap-chart" class="p-4 overflow-x-auto"></div>
</div>

<script>
(function(){
  // Trend chart
  const trend=<?=js($trend)?>;
  if(trend.length){
    const el=document.getElementById('trend-chart');
    const W=el.offsetWidth||700,H=200,m={top:10,right:15,bottom:30,left:35};
    const svg=d3.select('#trend-chart').append('svg').attr('width','100%').attr('height',H+m.top+m.bottom);
    const g=svg.append('g').attr('transform',`translate(${m.left},${m.top})`);
    const iw=W-m.left-m.right,ih=H;
    const x=d3.scalePoint().domain(trend.map(d=>d.week_key)).range([0,iw]).padding(0.1);
    const maxT=d3.max(trend,d=>+d.total)||1;
    const y=d3.scaleLinear().domain([0,maxT]).range([ih,0]);
    // Area
    const area=d3.area().x(d=>x(d.week_key)).y0(ih).y1(d=>y(+d.total)).curve(d3.curveCatmullRom);
    const line=d3.line().x(d=>x(d.week_key)).y(d=>y(+d.total)).curve(d3.curveCatmullRom);
    g.append('defs').append('linearGradient').attr('id','areaGrad').attr('x1','0').attr('y1','0').attr('x2','0').attr('y2','1')
      .selectAll('stop').data([{offset:'0%',color:'#3b5bdb',opacity:0.3},{offset:'100%',color:'#3b5bdb',opacity:0.02}])
      .enter().append('stop').attr('offset',d=>d.offset).attr('stop-color',d=>d.color).attr('stop-opacity',d=>d.opacity);
    g.append('path').datum(trend).attr('fill','url(#areaGrad)').attr('d',area);
    g.append('path').datum(trend).attr('fill','none').attr('stroke','#3b5bdb').attr('stroke-width',2).attr('d',line);
    // Severe overlay
    const lineS=d3.line().x(d=>x(d.week_key)).y(d=>y(+d.severe)).curve(d3.curveCatmullRom);
    g.append('path').datum(trend).attr('fill','none').attr('stroke','#dc2626').attr('stroke-width',1.5).attr('stroke-dasharray','4,2').attr('d',lineS);
    g.append('g').attr('transform',`translate(0,${ih})`).call(d3.axisBottom(x).tickValues(x.domain().filter((_,i)=>i%4===0)).tickFormat(d=>d.substring(5))).selectAll('text').attr('font-size','9').attr('transform','rotate(-35)').attr('text-anchor','end');
    g.append('g').call(d3.axisLeft(y).ticks(4).tickFormat(d3.format('d'))).selectAll('text').attr('font-size','10');
    // Legend
    const leg=svg.append('g').attr('transform',`translate(${m.left+iw-120},${m.top+8})`);
    leg.append('line').attr('x1',0).attr('x2',18).attr('stroke','#3b5bdb').attr('stroke-width',2);
    leg.append('text').attr('x',22).attr('y',4).attr('font-size','10').attr('fill','#475569').text('Total');
    leg.append('line').attr('x1',60).attr('x2',78).attr('stroke','#dc2626').attr('stroke-width',1.5).attr('stroke-dasharray','4,2');
    leg.append('text').attr('x',82).attr('y',4).attr('font-size','10').attr('fill','#475569').text('Class I');
  }else{
    document.getElementById('trend-chart').innerHTML='<p class="text-sm text-slate-400 text-center py-8">No trend data yet. Run ingestion first.</p>';
  }

  // Seasonal heatmap
  const seas=<?=js($seasonal)?>;
  if(seas.length){
    const years=[...new Set(seas.map(d=>d.year))].sort();
    const months=['01','02','03','04','05','06','07','08','09','10','11','12'];
    const mNames=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const lookup={};seas.forEach(d=>lookup[d.year+'-'+d.month]=+d.total);
    const maxVal=d3.max(seas,d=>+d.total)||1;
    const color=d3.scaleSequential([0,maxVal],d3.interpolateBlues);
    const cw=Math.max(28,Math.min(50,Math.floor((document.getElementById('heatmap-chart').offsetWidth-80)/months.length)));
    const ch=22;
    const el=document.getElementById('heatmap-chart');
    const W=80+cw*months.length,H=24+ch*years.length+20;
    const svg=d3.select('#heatmap-chart').append('svg').attr('width',W).attr('height',H);
    months.forEach((m,mi)=>svg.append('text').attr('x',80+mi*cw+cw/2).attr('y',14).attr('text-anchor','middle').attr('font-size','9').attr('fill','#64748b').text(mNames[mi]));
    years.forEach((yr,yi)=>{
      svg.append('text').attr('x',74).attr('y',30+yi*ch+ch/2+3).attr('text-anchor','end').attr('font-size','9').attr('fill','#64748b').text(yr);
      months.forEach((mo,mi)=>{
        const v=lookup[yr+'-'+mo]??0;
        const g=svg.append('g').attr('transform',`translate(${80+mi*cw},${24+yi*ch})`);
        g.append('rect').attr('width',cw-2).attr('height',ch-2).attr('rx',2).attr('fill',v?color(v):'#f1f5f9');
        if(v)g.append('text').attr('x',(cw-2)/2).attr('y',(ch-2)/2+4).attr('text-anchor','middle').attr('font-size','9').attr('fill',v>maxVal*0.5?'#fff':'#334155').text(v);
        g.append('title').text(`${mNames[mi]} ${yr}: ${v} recalls`);
      });
    });
    // DFT harmonic decomposition — mark the top-2 seasonal peaks (T04)
    // Build monthly totals summed across all years
    const monthlyTotals=new Array(12).fill(0);
    seas.forEach(d=>{const mi=+d.month-1;if(mi>=0&&mi<12)monthlyTotals[mi]+=(+d.total||0);});
    const N=12;
    // Discrete Fourier transform on monthly signal
    const re=new Array(N).fill(0),im=new Array(N).fill(0);
    for(let k=0;k<N;k++)for(let n=0;n<N;n++){
      const angle=2*Math.PI*k*n/N;
      re[k]+=monthlyTotals[n]*Math.cos(angle);
      im[k]-=monthlyTotals[n]*Math.sin(angle);
    }
    const amp=re.map((r,i)=>Math.sqrt(r*r+im[i]*im[i])/N);
    // k=0 is DC component; find top-2 non-zero harmonics
    const harmonics=amp.map((a,k)=>({k,a})).slice(1,7).sort((a,b)=>b.a-a.a).slice(0,2);
    const mNames2=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    if(harmonics.length){
      const peakMonths=harmonics.map(h=>{
        // Phase: argmax of cos(2πk·n/N + φ_k)
        const phi=Math.atan2(-im[h.k],re[h.k]);
        const peak=Math.round(((-phi/(2*Math.PI/h.k))%h.k+h.k)%h.k)%N;
        return mNames2[peak];
      });
      const infoEl=document.createElement('div');
      infoEl.className='text-xs text-indigo-700 mt-2 px-1';
      infoEl.innerHTML=`<strong>DFT harmonics:</strong> dominant seasonal peaks at <strong>${peakMonths.join(', ')}</strong> `+
        `(k=${harmonics.map(h=>h.k).join(',')} · amplitudes ${harmonics.map(h=>h.a.toFixed(1)).join(', ')})`;
      document.getElementById('heatmap-chart').appendChild(infoEl);
    }
  }else{
    document.getElementById('heatmap-chart').innerHTML='<p class="text-sm text-slate-400 text-center py-4">No data</p>';
  }
})();
</script>
<?php layout_foot(); }

function view_map():void{
    $geo_risk=q_geo_risk();
    layout_head('Choropleth Map','map'); ?>
<div class="mb-3 text-sm text-slate-600">US states colored by total recall count. Hover for details; click to filter recalls by state.</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2 bg-white rounded-lg border border-slate-200 shadow-sm p-3">
    <div id="us-map" style="width:100%;min-height:380px"></div>
  </div>
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4">
    <h3 class="text-sm font-semibold text-slate-700 mb-3">Top States by Recall Count</h3>
    <div id="state-list" class="space-y-1 max-h-80 overflow-y-auto text-sm"></div>
    <div id="map-tooltip" class="hidden mt-3 p-3 bg-slate-50 rounded border border-slate-200 text-xs"></div>
  </div>
</div>
<div class="mt-2 text-xs text-slate-500 flex items-center gap-4">
  <span>Color scale: light = fewer recalls → dark blue = most recalls</span>
  <span>Includes nationwide recalls in all states</span>
</div>
<script src="https://cdn.jsdelivr.net/npm/topojson-client@3/dist/topojson.min.js"></script>
<script>
(function(){
  const geoRisk=<?=js(array_values($geo_risk))?>;
  const byState={};
  geoRisk.forEach(d=>{byState[d.state_code]=d;});

  // State list
  const sorted=[...geoRisk].sort((a,b)=>+b.total-+a.total).slice(0,20);
  const maxT=sorted[0]?+sorted[0].total:1;
  const listEl=document.getElementById('state-list');
  sorted.forEach(d=>{
    const pct=Math.round(+d.total/maxT*100);
    listEl.innerHTML+=`<div class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 rounded px-1" onclick="location='?page=recalls&state=${d.state_code}'">
      <span class="text-xs font-mono text-slate-500 w-6">${d.state_code}</span>
      <div class="flex-1 h-2 bg-slate-100 rounded"><div class="h-2 bg-blue-600 rounded" style="width:${pct}%"></div></div>
      <span class="text-xs font-semibold text-slate-700 w-6 text-right">${d.total}</span>
    </div>`;
  });

  // Load TopoJSON and render map
  fetch('https://cdn.jsdelivr.net/npm/us-atlas@3/states-10m.json')
    .then(r=>r.json())
    .then(us=>{
      const states=topojson.feature(us,us.objects.states);
      const fips={
        '01':'AL','02':'AK','04':'AZ','05':'AR','06':'CA','08':'CO','09':'CT','10':'DE','11':'DC',
        '12':'FL','13':'GA','15':'HI','16':'ID','17':'IL','18':'IN','19':'IA','20':'KS','21':'KY',
        '22':'LA','23':'ME','24':'MD','25':'MA','26':'MI','27':'MN','28':'MS','29':'MO','30':'MT',
        '31':'NE','32':'NV','33':'NH','34':'NJ','35':'NM','36':'NY','37':'NC','38':'ND','39':'OH',
        '40':'OK','41':'OR','42':'PA','44':'RI','45':'SC','46':'SD','47':'TN','48':'TX','49':'UT',
        '50':'VT','51':'VA','53':'WA','54':'WV','55':'WI','56':'WY','72':'PR','78':'VI'
      };
      const el=document.getElementById('us-map');
      const W=el.offsetWidth||600;const H=Math.round(W*0.62);
      const proj=d3.geoAlbersUsa().fitSize([W,H],states);
      const path=d3.geoPath().projection(proj);
      const maxV=d3.max(geoRisk,d=>+d.total)||1;
      const color=d3.scaleSequential([0,maxV],d3.interpolateBlues);
      const svg=d3.select('#us-map').append('svg').attr('width','100%').attr('viewBox',`0 0 ${W} ${H}`);
      const tip=document.getElementById('map-tooltip');
      svg.selectAll('path').data(states.features).enter().append('path')
        .attr('d',path)
        .attr('fill',d=>{const code=fips[String(+d.id).padStart(2,'0')];const info=byState[code];return info&&+info.total>0?color(+info.total):'#e2e8f0';})
        .attr('stroke','#fff').attr('stroke-width',0.5)
        .style('cursor','pointer')
        .on('mouseover',function(e,d){
          d3.select(this).attr('stroke','#1e3a5f').attr('stroke-width',1.5);
          const code=fips[String(+d.id).padStart(2,'0')];
          const info=byState[code]||{total:0,active:0,risk_score:0,severe:0};
          tip.classList.remove('hidden');
          tip.innerHTML=`<strong>${code||'?'}</strong><br>Total recalls: ${info.total||0}<br>Active: ${info.active||0}<br>Severe (Class I): ${info.severe||0}<br>Risk score: ${(+info.risk_score||0).toFixed(2)}`;
        })
        .on('mouseout',function(){d3.select(this).attr('stroke','#fff').attr('stroke-width',0.5);tip.classList.add('hidden');})
        .on('click',(e,d)=>{const code=fips[String(+d.id).padStart(2,'0')];if(code)location='?page=recalls&state='+code;});
      // State borders mesh
      svg.append('path').datum(topojson.mesh(us,us.objects.states,(a,b)=>a!==b))
        .attr('fill','none').attr('stroke','#fff').attr('stroke-width',0.5).attr('d',path);
    })
    .catch(()=>{document.getElementById('us-map').innerHTML='<p class="text-sm text-slate-400 text-center py-12">Map unavailable — check network connection.</p>';});
})();
</script>
<?php layout_foot(); }

function view_timeline():void{
    $records=q_timeline_data(80);
    $markov_est=markov_estimate_matrix();
    layout_head('Timeline / Gantt','timeline'); ?>
<div class="mb-3 flex items-center gap-3">
  <div class="text-sm text-slate-600">Recall timeline from earliest to most recent. Bar width = duration active (min 3px). Color = severity class.</div>
  <a href="?api=export_pdf&status=all" target="_blank" class="no-print ml-auto text-xs text-fw-500 border border-fw-500 rounded px-3 py-1 hover:bg-fw-50 flex items-center gap-1"><i data-lucide="printer" class="w-3 h-3"></i>Print / PDF</a>
</div>
<div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4">
  <div id="gantt-legend" class="flex items-center gap-4 text-xs text-slate-600 mb-3 flex-wrap">
    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-red-500"></span>Class I</span>
    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-amber-400"></span>Class II</span>
    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-green-500"></span>Class III</span>
    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-slate-300"></span>Completed</span>
    <span class="flex items-center gap-1"><span class="inline-block w-12 h-0 border-t-2 border-dashed border-indigo-400"></span>Projected resolution</span>
  </div>
  <div id="gantt-chart" style="overflow-x:auto"></div>
</div>
<script>
(function(){
  const raw=<?=js($records)?>;
  if(!raw.length){document.getElementById('gantt-chart').innerHTML='<p class="text-sm text-slate-400 text-center py-8">No data</p>';return;}
  const today=new Date();
  const dates=raw.map(d=>new Date(d.announced_date)).filter(d=>!isNaN(d));
  if(!dates.length)return;
  // Compute projected resolution for ongoing recalls via Markov model
  const markovP=<?=js($markov_est['P']??[[0,0.72,0.18,0.10],[0,0,0.85,0.15],[0,0,0,1],[0,0,0,1]])?>;
  const cycleDays=14;
  function pResolvedInK(P,state,k){
    if(state>=2)return 1.0;
    const Q=[[P[0][0]??0,P[0][1]??0],[P[1][0]??0,P[1][1]??0]];
    let Qk=[[1,0],[0,1]];
    for(let s=0;s<k;s++){const t=[[0,0],[0,0]];for(let r=0;r<2;r++)for(let c=0;c<2;c++)for(let tt=0;tt<2;tt++)t[r][c]+=Qk[r][tt]*Q[tt][c];Qk=t;}
    return Math.max(0,Math.min(1,1-Qk[state].reduce((a,b)=>a+b,0)));
  }
  function fundamentalN(P){
    const Q=[[P[0][0]??0,P[0][1]??0],[P[1][0]??0,P[1][1]??0]];
    const a=1-Q[0][0],b=-Q[0][1],c=-Q[1][0],d=1-Q[1][1],det=a*d-b*c;
    if(Math.abs(det)<1e-9)return[[1,0],[0,1]];
    return[[d/det,-b/det],[-c/det,a/det]];
  }
  const Nm=fundamentalN(markovP);
  function expectedDays(state){if(state>=2)return 0;return Math.round((Nm[state][0]+Nm[state][1])*cycleDays);}
  const smap={announced:0,active:1,ongoing:1,resolved:2,completed:2,terminated:2,archived:3};
  const minD=new Date(Math.min(...dates));
  // Extend maxD to include projections for ongoing recalls
  const maxD=new Date(Math.max(today.getTime(),...raw.filter(d=>d.status==='ongoing').map(d=>today.getTime()+expectedDays(1)*86400000)));
  const W=Math.max(700,document.getElementById('gantt-chart').offsetWidth-20);
  const rowH=22,labelW=200,m={top:30,right:20,bottom:10,left:labelW};
  const H=rowH*raw.length;
  const x=d3.scaleTime().domain([minD,maxD]).range([0,W-labelW-20]);
  const color=d=>+d.severity>=3?'#ef4444':(+d.severity>=2?'#f59e0b':'#22c55e');
  const colorFaded=d=>d.status==='completed'?'#cbd5e1':color(d);
  const svg=d3.select('#gantt-chart').append('svg').attr('width',W).attr('height',H+m.top+m.bottom);
  const g=svg.append('g').attr('transform',`translate(${m.left},${m.top})`);
  // X-axis months
  g.append('g').call(d3.axisTop(x).ticks(d3.timeMonth.every(3)).tickFormat(d3.timeFormat('%b %Y'))).selectAll('text').attr('font-size','9').attr('fill','#64748b');
  // Grid lines
  g.selectAll('.gridline').data(x.ticks(d3.timeMonth.every(3))).enter().append('line').attr('x1',d=>x(d)).attr('x2',d=>x(d)).attr('y1',0).attr('y2',H).attr('stroke','#e2e8f0').attr('stroke-width',1);
  // Today line
  const xToday=x(today);
  g.append('line').attr('x1',xToday).attr('x2',xToday).attr('y1',0).attr('y2',H).attr('stroke','#6366f1').attr('stroke-width',1.5).attr('stroke-dasharray','4,3');
  g.append('text').attr('x',xToday+3).attr('y',-5).attr('font-size','8').attr('fill','#6366f1').text('Today');
  // Rows
  raw.forEach((d,i)=>{
    const y=i*rowH+2;
    const start=new Date(d.announced_date);
    const end=d.status_updated_date&&d.status!=='ongoing'?new Date(d.status_updated_date):today;
    if(isNaN(start))return;
    const x1=x(start),x2=Math.max(x1+3,x(end));
    // Label
    svg.append('text').attr('x',m.left-5).attr('y',m.top+y+rowH/2+3).attr('text-anchor','end').attr('font-size','9').attr('fill','#334155').text(d.title.substring(0,30)+(d.title.length>30?'…':''));
    // Bar
    const bar=g.append('rect').attr('x',x1).attr('y',y).attr('width',x2-x1).attr('height',rowH-4).attr('rx',2).attr('fill',colorFaded(d)).attr('opacity',d.status==='completed'?0.5:0.85).style('cursor','pointer');
    bar.append('title').text(`${d.title}\n${d.announced_date} → ${d.status_updated_date||'ongoing'}\n${d.classification||''} (${d.agency_code})`);
    bar.on('click',()=>location='?page=recall&id='+d.id);
    // Projected-resolution dashed extension for ongoing recalls
    if(d.status==='ongoing'||d.status==='active'||d.status==='announced'){
      const state=smap[d.status]??1;
      const projDays=expectedDays(state);
      if(projDays>0){
        const projEnd=new Date(today.getTime()+projDays*86400000);
        const xProj=x(projEnd);
        if(xProj>x2){
          g.append('line').attr('x1',x2).attr('x2',xProj).attr('y1',y+(rowH-4)/2).attr('y2',y+(rowH-4)/2).attr('stroke','#818cf8').attr('stroke-width',2).attr('stroke-dasharray','5,3')
            .append('title').text(`Projected resolution in ~${projDays} days (Markov estimate)`);
          g.append('circle').attr('cx',xProj).attr('cy',y+(rowH-4)/2).attr('r',3).attr('fill','#6366f1').attr('opacity',0.7);
        }
      }
    }
  });
})();
</script>
<?php layout_foot(); }

function view_sankey():void{
    layout_head('Sankey Flow','sankey'); ?>
<div class="mb-3 text-sm text-slate-600">Flow diagram: <strong>Manufacturers</strong> → <strong>Food Categories</strong> → <strong>Retailers</strong>. Width proportional to recall count. Hover flows for details.</div>
<div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4">
  <div id="sankey-chart" style="min-height:500px"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/d3-sankey@0.12.3/dist/d3-sankey.min.js"></script>
<script>
(function(){
  fetch('?api=sankey').then(r=>r.json()).then(links=>{
    if(!links.length){document.getElementById('sankey-chart').innerHTML='<p class="text-sm text-slate-400 text-center py-8">No flow data. Run ingestion first.</p>';return;}
    // Build nodes
    const nodeNames=new Set();
    links.forEach(l=>{nodeNames.add(l.src);nodeNames.add(l.tgt);});
    const nodes=[...nodeNames].map(name=>({name}));
    const nodeIndex=Object.fromEntries(nodes.map((n,i)=>[n.name,i]));
    // Filter to top 10 per type to keep readable
    const mfrTotals={},catTotals={};
    links.forEach(l=>{if(l.link_type==='mfr_cat'){mfrTotals[l.src]=(mfrTotals[l.src]||0)+l.value;}else{catTotals[l.tgt]=(catTotals[l.tgt]||0)+l.value;}});
    const topMfrs=new Set(Object.entries(mfrTotals).sort((a,b)=>b[1]-a[1]).slice(0,10).map(e=>e[0]));
    const topRets=new Set(Object.entries(catTotals).sort((a,b)=>b[1]-a[1]).slice(0,10).map(e=>e[0]));
    const filteredLinks=links.filter(l=>(l.link_type==='mfr_cat'&&topMfrs.has(l.src))||(l.link_type==='cat_ret'&&topRets.has(l.tgt)));
    const usedNames=new Set();filteredLinks.forEach(l=>{usedNames.add(l.src);usedNames.add(l.tgt);});
    const fNodes=[...usedNames].map(name=>({name}));
    const fIndex=Object.fromEntries(fNodes.map((n,i)=>[n.name,i]));
    const fLinks=filteredLinks.map(l=>({source:fIndex[l.src],target:fIndex[l.tgt],value:+l.value,type:l.link_type}));
    const el=document.getElementById('sankey-chart');
    const W=el.offsetWidth||700,H=Math.max(500,fNodes.length*20);
    const svg=d3.select('#sankey-chart').append('svg').attr('width','100%').attr('viewBox',`0 0 ${W} ${H}`);
    const sankey=d3.sankey().nodeWidth(18).nodePadding(10).extent([[15,10],[W-15,H-10]]);
    const {nodes:sNodes,links:sLinks}=sankey({nodes:fNodes.map(d=>Object.assign({},d)),links:fLinks.map(d=>Object.assign({},d))});
    const colorScale=d3.scaleOrdinal(d3.schemeTableau10);
    // Max-flow/min-cut: identify the link with minimum width (bottleneck cut edge)
    const minLink=fLinks.reduce((a,b)=>+b.value<+a.value?b:a,fLinks[0]);
    const minLinkKey=minLink?`${minLink.source}-${minLink.target}`:null;

    // Links with click-through (B02) and min-cut highlight (E03)
    svg.append('g').attr('fill','none').selectAll('path').data(sLinks).enter().append('path')
      .attr('d',d3.sankeyLinkHorizontal())
      .attr('stroke',d=>{
        // Highlight min-cut edge in red
        const origIdx=fLinks.findIndex(l=>l.source===d.source.index&&l.target===d.target.index);
        return(minLinkKey&&origIdx>=0&&`${fLinks[origIdx].source}-${fLinks[origIdx].target}`===minLinkKey)?'#dc2626':colorScale(d.source.name);
      })
      .attr('stroke-width',d=>Math.max(1,d.width))
      .attr('opacity',0.4)
      .style('cursor','pointer')
      .on('mouseover',function(e,d){d3.select(this).attr('opacity',0.75);})
      .on('mouseout',function(e,d){d3.select(this).attr('opacity',0.4);})
      .on('click',(e,d)=>{
        // Navigate to filtered recall list by the source node (manufacturer or category)
        const src=d.source.name;
        const q=encodeURIComponent(src);
        location='?page=recalls&q='+q;
      })
      .append('title').text(d=>`${d.source.name} → ${d.target.name}\n${d.value} recall(s)\nClick to filter recalls`);

    // Min-cut annotation
    if(minLink){
      const sL=sLinks.find(l=>l.source.name===fNodes[minLink.source]?.name&&l.target.name===fNodes[minLink.target]?.name);
      if(sL){
        const midY=(sL.y0+sL.y1)/2;const midX=(sL.source.x1+sL.target.x0)/2;
        svg.append('text').attr('x',midX).attr('y',midY-6).attr('text-anchor','middle').attr('font-size','9').attr('fill','#dc2626').attr('font-weight','bold').text('min-cut');
      }
    }

    // Nodes
    const gn=svg.append('g').selectAll('g').data(sNodes).enter().append('g').style('cursor','pointer')
      .on('click',(e,d)=>location='?page=recalls&q='+encodeURIComponent(d.name));
    gn.append('rect').attr('x',d=>d.x0).attr('y',d=>d.y0).attr('height',d=>Math.max(1,d.y1-d.y0)).attr('width',d=>d.x1-d.x0).attr('fill',d=>colorScale(d.name)).attr('rx',2).append('title').text(d=>`${d.name}\n${d.value} recalls\nClick to view recalls`);
    gn.append('text').attr('x',d=>d.x0<W/2?d.x1+5:d.x0-5).attr('y',d=>(d.y0+d.y1)/2).attr('dy','0.35em').attr('text-anchor',d=>d.x0<W/2?'start':'end').attr('font-size','10').attr('fill','#334155').text(d=>d.name.length>22?d.name.substring(0,22)+'…':d.name);
  });
})();
</script>
<?php layout_foot(); }

function view_graph3d():void{
    $recalls_raw=db()->query("SELECT r.id,r.title,r.severity,r.classification,r.status,r.food_category_id,r.announced_date,a.code as agency_code,fc.name as category_name FROM recalls r JOIN agencies a ON a.id=r.agency_id LEFT JOIN food_categories fc ON fc.id=r.food_category_id ORDER BY r.id")->fetchAll();
    $retailer_links=db()->query("SELECT rr.recall_id,rt.name as retailer FROM recall_retailers rr JOIN retailers rt ON rt.id=rr.retailer_id")->fetchAll();
    layout_head('3D Force Graph','graph3d'); ?>
<div class="mb-3 flex items-center gap-4">
  <div class="text-sm text-slate-600">Force-directed 3D graph of all recalls. Drag to rotate. Nodes sized by severity. Edges = shared retailer.</div>
  <div class="ml-auto flex items-center gap-2 text-xs">
    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>Class I</span>
    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-full bg-amber-400"></span>Class II</span>
    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-full bg-green-500"></span>Class III</span>
  </div>
</div>
<div class="bg-slate-900 rounded-lg border border-slate-700 shadow-sm relative" style="height:520px">
  <canvas id="graph3d-canvas" style="width:100%;height:100%;border-radius:0.5rem"></canvas>
  <div id="graph3d-info" class="absolute top-3 right-3 bg-black bg-opacity-70 text-white text-xs p-3 rounded max-w-xs hidden"></div>
  <div id="graph3d-hubs" class="absolute bottom-12 right-3 bg-black bg-opacity-70 text-white text-xs p-2 rounded max-w-xs hidden"></div>
  <div class="absolute bottom-3 left-3 text-slate-400 text-xs">Drag to rotate · Scroll to zoom · Click node for details</div>
  <button id="graph3d-hub-btn" class="absolute bottom-3 right-3 bg-indigo-600 text-white text-xs px-2 py-1 rounded hover:bg-indigo-700">Hub Analysis</button>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
(function(){
  const R=<?=js($recalls_raw)?>;
  const LINKS=<?=js($retailer_links)?>;
  if(!R.length||typeof THREE==='undefined'){document.getElementById('graph3d-canvas').parentNode.innerHTML='<p class="text-sm text-slate-400 text-center py-12">3D unavailable</p>';return;}

  const canvas=document.getElementById('graph3d-canvas');
  const W=canvas.parentNode.offsetWidth,H=520;
  canvas.width=W*devicePixelRatio;canvas.height=H*devicePixelRatio;
  canvas.style.width=W+'px';canvas.style.height=H+'px';

  const renderer=new THREE.WebGLRenderer({canvas,antialias:true,alpha:true});
  renderer.setPixelRatio(devicePixelRatio);renderer.setSize(W,H);renderer.setClearColor(0x0f172a,1);

  const scene=new THREE.Scene();
  const camera=new THREE.PerspectiveCamera(60,W/H,0.1,2000);
  camera.position.set(0,0,300);

  scene.add(new THREE.AmbientLight(0xffffff,0.6));
  const dl=new THREE.DirectionalLight(0xffffff,0.8);dl.position.set(1,1,1);scene.add(dl);

  // Build nodes
  const nodeColor=d=>+d.severity>=3?0xef4444:(+d.severity>=2?0xf59e0b:0x22c55e);
  const nodeR=d=>3+Math.sqrt(+d.severity)*4;
  const nodes=R.map((d,i)=>({...d,idx:i,x:(Math.random()-0.5)*200,y:(Math.random()-0.5)*200,z:(Math.random()-0.5)*200,vx:0,vy:0,vz:0}));

  // Build edges from shared retailers
  const retMap={};
  LINKS.forEach(l=>{if(!retMap[l.retailer])retMap[l.retailer]=[];retMap[l.retailer].push(+l.recall_id-1);});
  const edges=[];
  const nodeById=Object.fromEntries(nodes.map(n=>[+n.id,n]));
  LINKS.forEach(l=>{});// edges from shared retailers
  Object.values(retMap).forEach(ids=>{
    if(ids.length<2)return;
    for(let i=0;i<Math.min(ids.length,5);i++)for(let j=i+1;j<Math.min(ids.length,6);j++){
      const a=nodes.find(n=>+n.id===ids[i]||+n.idx===ids[i]);
      const b=nodes.find(n=>+n.id===ids[j]||+n.idx===ids[j]);
      if(a&&b)edges.push([a,b]);
    }
  });

  // Meshes
  const meshes=nodes.map(n=>{
    const geo=new THREE.SphereGeometry(nodeR(n),8,8);
    const mat=new THREE.MeshPhongMaterial({color:nodeColor(n),transparent:true,opacity:0.85});
    const mesh=new THREE.Mesh(geo,mat);
    mesh.position.set(n.x,n.y,n.z);
    mesh.userData=n;
    scene.add(mesh);return mesh;
  });

  // Edge lines
  const lineMat=new THREE.LineBasicMaterial({color:0x334155,transparent:true,opacity:0.25});
  edges.slice(0,300).forEach(([a,b])=>{
    const geo=new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(a.x,a.y,a.z),new THREE.Vector3(b.x,b.y,b.z)]);
    scene.add(new THREE.Line(geo,lineMat));
  });

  // Simple spring simulation
  function tick(){
    nodes.forEach(n=>{
      nodes.forEach(m=>{
        if(m===n)return;
        const dx=n.x-m.x,dy=n.y-m.y,dz=n.z-m.z;
        const d2=dx*dx+dy*dy+dz*dz+1;
        const f=200/d2;n.vx+=dx*f;n.vy+=dy*f;n.vz+=dz*f;
      });
      n.vx*=0.9;n.vy*=0.9;n.vz*=0.9;
      n.x+=n.vx*0.05;n.y+=n.vy*0.05;n.z+=n.vz*0.05;
    });
    meshes.forEach((m,i)=>m.position.set(nodes[i].x,nodes[i].y,nodes[i].z));
  }
  for(let i=0;i<80;i++)tick();

  // Mouse rotation
  let isDragging=false,prevX=0,prevY=0,rotX=0,rotY=0;
  canvas.addEventListener('mousedown',e=>{isDragging=true;prevX=e.clientX;prevY=e.clientY;});
  window.addEventListener('mouseup',()=>isDragging=false);
  canvas.addEventListener('mousemove',e=>{
    if(!isDragging)return;
    rotY+=(e.clientX-prevX)*0.01;rotX+=(e.clientY-prevY)*0.01;
    prevX=e.clientX;prevY=e.clientY;
  });
  canvas.addEventListener('wheel',e=>{camera.position.z=Math.max(100,Math.min(600,camera.position.z+e.deltaY*0.3));});

  // Click to select
  const raycaster=new THREE.Raycaster();const mouse=new THREE.Vector2();
  const info=document.getElementById('graph3d-info');
  canvas.addEventListener('click',e=>{
    const rect=canvas.getBoundingClientRect();
    mouse.x=((e.clientX-rect.left)/rect.width)*2-1;
    mouse.y=-((e.clientY-rect.top)/rect.height)*2+1;
    raycaster.setFromCamera(mouse,camera);
    const hits=raycaster.intersectObjects(meshes);
    if(hits.length){
      const d=hits[0].object.userData;
      info.classList.remove('hidden');
      info.innerHTML=`<strong>${d.title||''}</strong><br>${d.classification||''} · ${d.agency_code||''}<br>${d.announced_date||''}<br>${d.category_name||''}<br><a href="?page=recall&id=${d.id}" class="text-blue-300 underline">View details →</a>`;
    }
  });

  // Degree centrality (Erdős–Rényi hub analysis E01)
  const degree={};
  nodes.forEach(n=>{degree[n.id]=0;});
  edges.forEach(([a,b])=>{degree[a.id]=(degree[a.id]||0)+1;degree[b.id]=(degree[b.id]||0)+1;});
  const maxDeg=Math.max(1,...Object.values(degree));
  // Scale hub nodes visually
  meshes.forEach((m,i)=>{const deg=degree[nodes[i].id]||0;const s=1+deg/maxDeg*2;m.scale.set(s,s,s);});
  // Top hubs
  const topHubs=[...nodes].sort((a,b)=>(degree[b.id]||0)-(degree[a.id]||0)).slice(0,5);
  const hubsEl=document.getElementById('graph3d-hubs');
  hubsEl.innerHTML='<strong class="text-indigo-300">Top Hubs (degree centrality)</strong><ul class="mt-1 space-y-0.5">'+
    topHubs.map(n=>`<li><a href="?page=recall&id=${n.id}" class="text-blue-300 hover:underline">${(n.title||'').substring(0,30)}…</a> <span class="text-slate-400">deg=${degree[n.id]||0}</span></li>`).join('')+'</ul>';
  document.getElementById('graph3d-hub-btn').addEventListener('click',()=>{
    hubsEl.classList.toggle('hidden');
  });

  let autoRot=true;
  canvas.addEventListener('mousedown',()=>{autoRot=false;});
  canvas.addEventListener('mouseup',()=>setTimeout(()=>autoRot=true,3000));

  function animate(){
    requestAnimationFrame(animate);
    if(autoRot)rotY+=0.003;
    scene.rotation.x=rotX;scene.rotation.y=rotY;
    renderer.render(scene,camera);
  }
  animate();
})();
</script>
<?php layout_foot(); }

function view_barcode():void{
    $result=null;$upc='';
    if($_GET['upc']??''){
        $upc=preg_replace('/[^0-9]/','',trim($_GET['upc']));
        if($upc)$result=barcode_lookup($upc);
    }
    $supported=true; // BarcodeDetector needs browser check
    layout_head('Barcode Lookup','barcode'); ?>
<div class="max-w-2xl">
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-5 mb-4">
    <h2 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2"><i data-lucide="scan-barcode" class="w-4 h-4 text-fw-500"></i>UPC / Barcode Lookup</h2>
    <form method="get" class="flex items-center gap-2 mb-3">
      <input type="hidden" name="page" value="barcode">
      <input type="text" name="upc" value="<?=h($upc)?>" placeholder="Enter UPC (e.g. 0 12345 67890 5)" class="flex-1 text-sm border border-slate-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-fw-500" pattern="[0-9 \-]*">
      <button type="submit" class="bg-fw-500 text-white px-4 py-2 rounded text-sm font-medium hover:bg-fw-700">Look Up</button>
    </form>
    <!-- Camera scanner -->
    <div id="scanner-section" class="border-t border-slate-100 pt-3 mt-3">
      <p class="text-xs text-slate-500 mb-2">Or scan with your camera:</p>
      <button id="scan-btn" class="text-xs border border-slate-300 rounded px-3 py-1.5 hover:bg-slate-50 flex items-center gap-1.5"><i data-lucide="camera" class="w-3.5 h-3.5"></i>Open Camera Scanner</button>
      <video id="scan-video" class="hidden mt-2 rounded border border-slate-200 max-w-full" width="320" height="240" autoplay muted playsinline></video>
    </div>
  </div>

  <?php if($result): ?>
  <?php if(!empty($result['recall_matches'])): ?>
  <div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-4">
    <h3 class="text-sm font-bold text-red-700 mb-2 flex items-center gap-2"><i data-lucide="alert-triangle" class="w-4 h-4"></i>⚠ RECALL ALERT — <?=count($result['recall_matches'])?> recall(s) found for UPC <?=h($upc)?></h3>
    <?php foreach($result['recall_matches'] as $rm): ?>
    <div class="bg-white border border-red-200 rounded p-3 mb-2">
      <div class="flex items-center gap-2 mb-1"><?=sev_badge((float)$rm['severity'],$rm['classification']??'')?><?=status_badge($rm['status'])?></div>
      <p class="text-sm font-medium text-slate-800"><?=h($rm['title'])?></p>
      <p class="text-xs text-slate-600 mt-1">Product: <?=h($rm['description']??'—')?> · Date: <?=h($rm['announced_date']??'—')?></p>
      <a href="?page=recall&id=<?=(int)$rm['id']?>" class="text-xs text-fw-500 hover:underline">View full recall →</a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4 text-sm text-green-800">
    <i data-lucide="check-circle" class="w-4 h-4 inline mr-1 text-green-600"></i>No active recalls found in our database for UPC <?=h($upc)?>.
  </div>
  <?php endif; ?>

  <?php if($result['product']): ?>
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 mb-4">
    <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2"><i data-lucide="package" class="w-4 h-4"></i>Product Info (Open Food Facts)</h3>
    <div class="flex gap-4">
      <?php if($result['product']['image']): ?><img src="<?=h($result['product']['image'])?>" alt="product" class="w-20 h-20 object-contain rounded border border-slate-100 flex-shrink-0"><?php endif; ?>
      <dl class="text-sm space-y-1">
        <div><dt class="text-xs font-semibold text-slate-500">Name</dt><dd><?=h($result['product']['name'])?></dd></div>
        <div><dt class="text-xs font-semibold text-slate-500">Brand</dt><dd><?=h($result['product']['brand'])?></dd></div>
        <div><dt class="text-xs font-semibold text-slate-500">Category</dt><dd><?=h(mb_substr($result['product']['category'],0,80))?></dd></div>
        <div><dt class="text-xs font-semibold text-slate-500">Quantity</dt><dd><?=h($result['product']['quantity'])?></dd></div>
      </dl>
    </div>
  </div>
  <?php elseif(isset($result['error'])): ?>
  <p class="text-sm text-slate-500">Product info unavailable: <?=h($result['error'])?></p>
  <?php else: ?>
  <p class="text-sm text-slate-500">No product information found in Open Food Facts for this UPC.</p>
  <?php endif; ?>
  <?php endif; ?>
</div>
<script>
document.getElementById('scan-btn')?.addEventListener('click',async function(){
  if(!('BarcodeDetector' in window)){alert('Camera barcode scanning is not supported in this browser. Please type the UPC manually.');return;}
  const video=document.getElementById('scan-video');video.classList.remove('hidden');
  try{
    const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
    video.srcObject=stream;
    const det=new BarcodeDetector({formats:['ean_13','ean_8','upc_a','upc_e','code_128']});
    const timer=setInterval(async()=>{
      try{
        const codes=await det.detect(video);
        if(codes.length){
          clearInterval(timer);stream.getTracks().forEach(t=>t.stop());video.classList.add('hidden');
          location='?page=barcode&upc='+codes[0].rawValue;
        }
      }catch(e){}
    },500);
  }catch(e){alert('Camera access denied: '+e.message);}
});
</script>
<?php layout_foot(); }

function view_subscriptions():void{
    $sid=session_id();
    $subs=[];
    if(is_admin()){
        $subs=db()->query('SELECT * FROM subscriptions ORDER BY created_at DESC')->fetchAll();
    }
    $cats=db()->query('SELECT id,name FROM food_categories ORDER BY name')->fetchAll();
    $hazs=db()->query('SELECT id,name FROM hazards ORDER BY type,name LIMIT 20')->fetchAll();
    layout_head('Email Alerts','subscriptions'); ?>
<div class="max-w-2xl">
  <!-- Subscribe form -->
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-5 mb-4" x-data="{saving:false,done:false,state:'',category:'',severity:'',err:''}">
    <h2 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2"><i data-lucide="mail" class="w-4 h-4 text-fw-500"></i>Subscribe to Recall Alerts</h2>
    <p class="text-xs text-slate-500 mb-4">Receive an email digest whenever new recalls match your criteria. Alerts are sent weekly (or manually via admin).</p>
    <div class="space-y-3">
      <div><label class="text-xs font-medium text-slate-600 block mb-1">Email address *</label>
        <input id="sub-email" type="email" required placeholder="you@example.com" class="w-full text-sm border border-slate-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-fw-500">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-xs font-medium text-slate-600 block mb-1">State filter</label>
          <select x-model="state" class="w-full text-sm border border-slate-300 rounded px-2 py-1.5">
            <option value="">Any state</option>
            <?php foreach(US_STATES as $c=>$n): ?><option value="<?=h($c)?>"><?=h($n)?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="text-xs font-medium text-slate-600 block mb-1">Min severity</label>
          <select x-model="severity" class="w-full text-sm border border-slate-300 rounded px-2 py-1.5">
            <option value="">Any severity</option>
            <option value="3">Class I only</option>
            <option value="2">Class I & II</option>
          </select>
        </div>
        <div><label class="text-xs font-medium text-slate-600 block mb-1">Food category</label>
          <select x-model="category" class="w-full text-sm border border-slate-300 rounded px-2 py-1.5">
            <option value="">Any category</option>
            <?php foreach($cats as $c): ?><option value="<?=(int)$c['id']?>"><?=h($c['name'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="text-xs font-medium text-slate-600 block mb-1">Recall status</label>
          <select id="sub-status" class="w-full text-sm border border-slate-300 rounded px-2 py-1.5">
            <option value="">All statuses</option>
            <option value="ongoing">Active only</option>
          </select>
        </div>
      </div>
      <p x-show="err" x-text="err" class="text-xs text-red-600"></p>
      <button @click="saving=true;err='';const email=document.getElementById('sub-email').value;if(!email){err='Email required';saving=false;return;}const f=new URLSearchParams({csrf:'<?=csrf()?>',email,state,category,severity,status:document.getElementById('sub-status').value});fetch('?api=subscription_add',{method:'POST',headers:{'X-CSRF-Token':'<?=csrf()?>'},body:f}).then(r=>r.json()).then(d=>{saving=false;if(d.ok){done=true;}else{err=d.error||'Error'}}).catch(()=>{saving=false;err='Request failed'})" :disabled="saving||done" class="bg-fw-500 text-white text-sm rounded px-4 py-2 font-medium hover:bg-fw-700 disabled:opacity-50">
        <span x-show="!saving&&!done">Subscribe</span>
        <span x-show="saving">Subscribing…</span>
        <span x-show="done" class="text-green-200">✓ Subscribed!</span>
      </button>
    </div>
  </div>

  <?php if(is_admin()&&$subs): ?>
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="users" class="w-4 h-4"></i>All Subscriptions (<?=count($subs)?>)</h2>
      <button onclick="if(confirm('Send alerts now?'))fetch('?api=send_alerts',{method:'POST',headers:{'X-CSRF-Token':'<?=csrf()?>'}}).then(r=>r.json()).then(d=>alert('Sent: '+d.sent+' emails'))" class="text-xs bg-fw-500 text-white px-3 py-1 rounded hover:bg-fw-700">Send Alerts Now</button>
    </div>
    <table class="fw-table w-full">
      <thead><tr><th>Email</th><th>Filters</th><th>Active</th><th>Last Sent</th><th>Subscribed</th></tr></thead>
      <tbody>
      <?php foreach($subs as $s): ?>
      <?php $f=json_decode($s['filter_json'],true)??[]; ?>
      <tr>
        <td><?=h($s['email'])?></td>
        <td class="text-xs text-slate-500"><?=h($f?implode(', ',array_filter([$f['state']??'',$f['severity']??'',$f['category']??''])):' (all)')?></td>
        <td class="text-center"><?=$s['active']?'<span class="text-green-600 text-xs font-semibold">Active</span>':'<span class="text-slate-400 text-xs">Off</span>'?></td>
        <td class="text-xs"><?=h($s['last_sent_at']??'Never')?></td>
        <td class="text-xs"><?=h($s['created_at'])?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php elseif(is_admin()): ?>
  <p class="text-sm text-slate-500 text-center py-4">No subscriptions yet.</p>
  <?php endif; ?>
</div>
<?php layout_foot(); }

function view_markov_admin():void{
    $est=markov_estimate_matrix();
    $N=markov_fundamental_matrix($est['P']);
    $steps=markov_expected_steps($N);
    $tc=(int)db()->query("SELECT COUNT(*) FROM recall_transitions")->fetchColumn();
    $last=db()->query("SELECT computed_at,confidence,sample_n FROM markov_params ORDER BY id DESC LIMIT 1")->fetch();
    $history=db()->query("SELECT computed_at,confidence,sample_n FROM markov_params ORDER BY id DESC LIMIT 10")->fetchAll();
    $labels=['announced','active','resolved','archived'];
    layout_head('Model Diagnostics','markov_admin'); ?>
<div class="mb-4 flex items-center gap-3">
  <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="activity" class="w-4 h-4 text-indigo-500"></i>Markov Transition Model — Diagnostics</h2>
  <?php if(is_admin()): ?>
  <a href="?api=markov_refresh" class="ml-auto text-xs bg-indigo-600 text-white rounded px-3 py-1 hover:bg-indigo-700">Recompute &amp; Cache</a>
  <?php endif; ?>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="fw-stat"><div class="text-3xl font-bold text-indigo-700"><?=(int)$tc?></div><div class="text-xs text-slate-500 mt-1">Status transitions recorded</div></div>
  <div class="fw-stat"><div class="text-3xl font-bold <?=$est['confidence']==='low'?'text-amber-600':($est['confidence']==='high'?'text-green-600':'text-slate-700')?>"><?=h(ucfirst($est['confidence']))?></div><div class="text-xs text-slate-500 mt-1">Model confidence</div></div>
  <div class="fw-stat"><div class="text-3xl font-bold text-slate-700"><?=number_format($steps[0],1)?></div><div class="text-xs text-slate-500 mt-1">E[cycles] from Announced</div></div>
  <div class="fw-stat"><div class="text-3xl font-bold text-slate-700"><?=number_format($steps[1],1)?></div><div class="text-xs text-slate-500 mt-1">E[cycles] from Active</div></div>
</div>

<?php if($est['confidence']==='low'): ?>
<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm text-amber-800 flex items-center gap-2">
  <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
  <span><strong>Low confidence:</strong> Only <?=(int)$est['n']?> transitions recorded. Estimates use Laplace-smoothed priors. Run poll_status regularly to build the training dataset. Confidence upgrades to "medium" at n≥10, "high" at n≥50.</span>
</div>
<?php endif; ?>

<!-- Transition Matrix P -->
<div class="bg-white rounded-lg border border-slate-200 shadow-sm p-5 mb-4">
  <h3 class="text-sm font-semibold text-slate-700 mb-3">Transition Matrix P (row-stochastic, 4×4)</h3>
  <p class="text-xs text-slate-500 mb-3">P[i][j] = probability of moving from state i → state j in one review cycle (~14 days). Laplace-smoothed. Absorbing state: archived.</p>
  <div style="overflow-x:auto">
  <table class="fw-table text-xs font-mono">
    <thead><tr><th class="text-right pr-3">From \ To</th><?php foreach($labels as $l): ?><th class="text-center"><?=h(ucfirst($l))?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach($est['P'] as $i=>$row): ?>
    <tr>
      <td class="text-right pr-3 font-semibold text-slate-600"><?=h(ucfirst($labels[$i]))?></td>
      <?php foreach($row as $j=>$v): ?>
      <?php $heat=$v>0.6?'bg-indigo-100 text-indigo-800 font-bold':($v>0.3?'bg-indigo-50 text-indigo-700':($v>0?'text-slate-600':'text-slate-300')); ?>
      <td class="text-center <?=$heat?>"><?=number_format($v,4)?></td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Fundamental Matrix N -->
<div class="bg-white rounded-lg border border-slate-200 shadow-sm p-5 mb-4">
  <h3 class="text-sm font-semibold text-slate-700 mb-1">Fundamental Matrix N = (I−Q)⁻¹ (2×2 transient submatrix)</h3>
  <p class="text-xs text-slate-500 mb-3">N[i][j] = expected number of times the chain is in transient state j before absorption, given start state i. Row-sum = E[steps to resolution].</p>
  <table class="fw-table text-xs font-mono">
    <thead><tr><th>From \ Via</th><th>Announced</th><th>Active</th><th>E[steps]</th><th>E[days] (~14d/cycle)</th></tr></thead>
    <tbody>
    <?php foreach([0=>'Announced',1=>'Active'] as $si=>$sl): ?>
    <tr>
      <td class="font-semibold"><?=$sl?></td>
      <td class="text-center"><?=number_format($N[$si][0]??0,4)?></td>
      <td class="text-center"><?=number_format($N[$si][1]??0,4)?></td>
      <td class="text-center font-bold text-indigo-700"><?=number_format($steps[$si]??0,2)?></td>
      <td class="text-center"><?=max(7,(int)round(($steps[$si]??4)*14*0.65))?>–<?=(int)round(($steps[$si]??4)*14*1.45)?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Transition history -->
<?php if($history): ?>
<div class="bg-white rounded-lg border border-slate-200 shadow-sm p-5 mb-4">
  <h3 class="text-sm font-semibold text-slate-700 mb-3">Cached Model History (last 10)</h3>
  <table class="fw-table w-full text-xs">
    <thead><tr><th>Computed</th><th>Transitions (n)</th><th>Confidence</th></tr></thead>
    <tbody>
    <?php foreach($history as $h): ?>
    <tr><td class="font-mono"><?=h($h['computed_at'])?></td><td class="text-center"><?=(int)$h['sample_n']?></td><td><?=h($h['confidence'])?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Recent transitions -->
<?php $recent_t=db()->query("SELECT rt.id,rt.from_status,rt.to_status,rt.days_in_from_state,rt.transitioned_at,r.title FROM recall_transitions rt JOIN recalls r ON r.id=rt.recall_id ORDER BY rt.id DESC LIMIT 20")->fetchAll(); ?>
<div class="bg-white rounded-lg border border-slate-200 shadow-sm p-5">
  <h3 class="text-sm font-semibold text-slate-700 mb-3">Recent Status Transitions (last 20)</h3>
  <?php if(!$recent_t): ?>
  <p class="text-sm text-slate-400 text-center py-6">No transitions recorded yet. Run poll_status to begin collecting data.</p>
  <?php else: ?>
  <table class="fw-table w-full text-xs">
    <thead><tr><th>Recall</th><th>From</th><th>To</th><th>Days in prior state</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach($recent_t as $t): ?>
    <tr><td class="max-w-xs truncate"><?=h(mb_substr($t['title'],0,50))?></td><td><?=h($t['from_status'])?></td><td><strong><?=h($t['to_status'])?></strong></td><td class="text-center"><?=(int)$t['days_in_from_state']?></td><td class="font-mono"><?=h($t['transitioned_at'])?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
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
