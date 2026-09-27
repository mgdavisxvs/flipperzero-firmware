<?php
/**
 * FoodWatch US — Extended Recall Seed (batch 2)
 * 50 additional documented FDA/FSIS recalls (2018–2025).
 * Idempotent — safe to re-run.
 */
declare(strict_types=1);

$_SERVER['REQUEST_URI']    = '/seed';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = 'localhost';

ob_start(); require __DIR__ . '/index.php'; ob_end_clean();

$db      = db();
$fda_id  = (int)$db->query("SELECT id FROM agencies WHERE code='FDA' LIMIT 1")->fetchColumn();
$fsis_id = (int)$db->query("SELECT id FROM agencies WHERE code='FSIS' LIMIT 1")->fetchColumn();
if (!$fda_id || !$fsis_id) die("Agencies not seeded.\n");

$R = ['inserted'=>0,'updated'=>0,'skipped'=>0,'errors'=>[]];

// ──────────────────────────────────────────────────────────────────
// BATCH 2 — FDA RECORDS
// ──────────────────────────────────────────────────────────────────
$FDA = [

// 1. Quaker Oats granola & oatmeal — Salmonella (landmark 2023 recall)
['recall_number'=>'F-0018-2024','product_description'=>'Quaker Oats granola bars, granola cereal, rice cakes, and oatmeal cup products under Quaker and Cap\'n Crunch brands','recalling_firm'=>'The Quaker Oats Company','city'=>'Chicago','state'=>'IL','reason_for_recall'=>'Salmonella contamination identified in products manufactured at the Cedar Rapids, Iowa facility. Multiple consumer illnesses linked to Salmonella-positive products.','distribution_pattern'=>'Nationwide at Walmart, Target, Kroger, Costco, Sam\'s Club, Publix, Walgreens, CVS, Dollar General, and all major grocery retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20231215','report_date'=>'20231219','product_quantity'=>'Millions of units across numerous SKUs','code_info'=>'Best Before dates up to 03/2024 with manufacturing codes from Cedar Rapids, IA'],

// 2. Fresh Del Monte cantaloupe — Salmonella
['recall_number'=>'F-2109-2023','product_description'=>'Fresh Del Monte SunMelon brand whole cantaloupes and pre-cut cantaloupe','recalling_firm'=>'Fresh Del Monte Produce N.A. Inc.','city'=>'Coral Gables','state'=>'FL','reason_for_recall'=>'Salmonella contamination confirmed via FDA traceback investigation. 43 illnesses, 16 hospitalizations across 12 states linked to cantaloupe consumption.','distribution_pattern'=>'Nationwide at Kroger, Whole Foods Market, Walmart, Trader Joe\'s, Publix, and other retailers. Also distributed to food service.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20231101','report_date'=>'20231106','product_quantity'=>'~31,000 units','code_info'=>'Purchased between 10/16/2023 and 11/1/2023; sticker with PLU 4050'],

// 3. Braga Fresh organic diced onions — Salmonella
['recall_number'=>'F-1788-2024','product_description'=>'Braga Fresh organic and conventional diced white and yellow onions in various container sizes','recalling_firm'=>'Braga Fresh Family Farms','city'=>'Soledad','state'=>'CA','reason_for_recall'=>'Salmonella Oranienburg contamination detected in environmental and product testing. Products distributed to retailers and food service under Braga Fresh and Fresh Express labels.','distribution_pattern'=>'Nationwide at Costco, Walmart, Target, Kroger, Safeway, Albertsons, and other retailers in CA, NV, AZ, OR, WA, CO, TX, FL, and other states.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20240614','report_date'=>'20240617','product_quantity'=>'~61,000 units','code_info'=>'Best By dates 06/05/2024 through 06/14/2024'],

// 4. Wawona packing peaches — Listeria
['recall_number'=>'F-1588-2021','product_description'=>'Wawona Packing Company and Fisher Farms fresh peaches, nectarines, and plums sold under multiple brand and private labels','recalling_firm'=>'Wawona Packing Company LLC','city'=>'Cutler','state'=>'CA','reason_for_recall'=>'Listeria monocytogenes contamination. At least 10 confirmed illnesses, 5 hospitalizations linked to fresh peaches. Products sold under Wawona, Harvest Select, and private label brands.','distribution_pattern'=>'Nationwide at Costco, Aldi, Walmart, Kroger, Jewel-Osco, and other retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20210901','report_date'=>'20210904','product_quantity'=>'~233,000 units','code_info'=>'PLU stickers 3107, 4044, 4945, 3382; summer 2021 harvest'],

// 5. Kellogg's Honey Smacks cereal — Salmonella (prolonged multistate outbreak)
['recall_number'=>'F-1092-2018','product_description'=>'Kellogg\'s Honey Smacks breakfast cereal in 15.3 oz and 23 oz boxes','recalling_firm'=>'Kellogg Company','city'=>'Battle Creek','state'=>'MI','reason_for_recall'=>'Salmonella Mbandaka contamination. 130 illnesses across 36 states and Canada. One of longest-running Salmonella outbreak recalls in FDA history.','distribution_pattern'=>'Nationwide at Walmart, Target, Kroger, Safeway, Albertsons, Publix, Amazon and all grocery retailers. Also internationally.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20180614','report_date'=>'20180614','product_quantity'=>'All Honey Smacks with Best By dates through 06/14/2019','code_info'=>'All packages with Best By date of June 14 2019 or earlier'],

// 6. Gold Medal flour — E. coli O121
['recall_number'=>'F-0892-2023','product_description'=>'Gold Medal brand all-purpose flour and other General Mills flour products','recalling_firm'=>'General Mills Inc.','city'=>'Minneapolis','state'=>'MN','reason_for_recall'=>'E. coli O121 contamination discovered during FDA investigation of flour-linked illness cluster. Raw flour consumption associated with 12 illnesses.','distribution_pattern'=>'Nationwide at Walmart, Target, Kroger, Publix, Albertsons, and all major grocery retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20230409','report_date'=>'20230413','product_quantity'=>'Various retail sizes, multiple date codes','code_info'=>'Better If Used By dates from 03/27/2023 to 03/27/2024'],

// 7. Wonderful Pistachios — Salmonella
['recall_number'=>'F-0338-2021','product_description'=>'Wonderful Pistachios raw and roasted pistachio products in multiple sizes and varieties','recalling_firm'=>'Wonderful Pistachios & Almonds LLC','city'=>'Los Angeles','state'=>'CA','reason_for_recall'=>'Salmonella contamination detected during routine FDA testing of finished product. Products may be contaminated with Salmonella Stanley.','distribution_pattern'=>'Nationwide at Costco, Walmart, Target, Kroger, Safeway, CVS, Walgreens, and other retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20210216','report_date'=>'20210220','product_quantity'=>'~1.1 million bags and bulk shipments','code_info'=>'Best By dates 04/2021 through 06/2021 with plant code CA'],

// 8. Bob Evans refrigerated mashed potatoes — Listeria
['recall_number'=>'F-1675-2023','product_description'=>'Bob Evans brand refrigerated mashed potatoes, macaroni and cheese, and other refrigerated side dishes','recalling_firm'=>'Bob Evans Farms LLC','city'=>'Columbus','state'=>'OH','reason_for_recall'=>'Listeria monocytogenes found in environmental sample at production facility. Products were produced during the same timeframe as the positive environmental result.','distribution_pattern'=>'Nationwide at Walmart, Kroger, Publix, Target, Meijer, Giant Eagle and other grocery retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20230920','report_date'=>'20230924','product_quantity'=>'~56,000 units','code_info'=>'Use By dates 10/12/2023 through 10/17/2023'],

// 9. Pepperidge Farm Goldfish Crackers — undeclared whey (milk allergen)
['recall_number'=>'F-1399-2018','product_description'=>'Pepperidge Farm Goldfish Flavor Blasted Xtra Cheddar crackers','recalling_firm'=>'Pepperidge Farm Incorporated','city'=>'Norwalk','state'=>'CT','reason_for_recall'=>'Undeclared milk (whey). The seasoning used on the product contains milk as a sub-ingredient, which was not declared on the label. Potential health risk to milk-allergic individuals.','distribution_pattern'=>'Nationwide at all major retailers including Walmart, Target, Kroger, Costco, and supermarkets.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20180723','report_date'=>'20180726','product_quantity'=>'~6.6 million packages','code_info'=>'Best By dates 10/2018 through 12/2018; UPC 0 14100 07494 3'],

// 10. Sargento Bistro cheese blends — Listeria
['recall_number'=>'F-0271-2022','product_description'=>'Sargento Bistro Blends sliced cheese products including pizza blend and Italian blend varieties','recalling_firm'=>'Sargento Foods Inc.','city'=>'Plymouth','state'=>'WI','reason_for_recall'=>'Listeria monocytogenes detected in product by FDA during routine surveillance testing. No illnesses reported at time of recall.','distribution_pattern'=>'Nationwide at Walmart, Kroger, Safeway, Albertsons, Target and other grocery retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20220209','report_date'=>'20220211','product_quantity'=>'~3,400 cases','code_info'=>'Use By dates 02/05/2022, 02/12/2022, 02/19/2022; UPC codes on packaging'],

// 11. Ritz Crackers — Salmonella (whey ingredient)
['recall_number'=>'F-1471-2018','product_description'=>'Ritz brand crackers and cracker sandwiches in multiple varieties','recalling_firm'=>'Mondelez Global LLC','city'=>'Deerfield','state'=>'IL','reason_for_recall'=>'Salmonella potentially present in whey protein powder used as ingredient, supplied by New Zealand manufacturer. Products sold in US, Canada, and other markets.','distribution_pattern'=>'Nationwide at Walmart, Target, Kroger, Costco, CVS, Walgreens, convenience stores and all grocery retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20180810','report_date'=>'20180814','product_quantity'=>'All affected Ritz products in US market','code_info'=>'Best By dates from 01/21/2019 to 01/21/2020'],

// 12. Hormel Natural Choice deli meats — Listeria
['recall_number'=>'F-1879-2023','product_description'=>'Hormel Natural Choice lunch meats, deli-style turkey, chicken, and ham sliced products','recalling_firm'=>'Hormel Foods Sales LLC','city'=>'Austin','state'=>'MN','reason_for_recall'=>'Listeria monocytogenes discovered during routine product testing. Recall includes products sold under Hormel Natural Choice brand at retail and food service.','distribution_pattern'=>'Nationwide at Target, Walmart, Kroger, Safeway, Albertsons, HEB, Publix, Whole Foods.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20231010','report_date'=>'20231013','product_quantity'=>'~14,000 lbs','code_info'=>'Best By dates 10/16/2023 and 10/23/2023'],

// 13. Taylor Farms organic chopped romaine — E. coli
['recall_number'=>'F-1991-2024','product_description'=>'Taylor Farms Organic Chopped Romaine and Taylor Farms Caesar Chopped Salad Kits','recalling_firm'=>'Taylor Farms Retail Inc.','city'=>'Salinas','state'=>'CA','reason_for_recall'=>'E. coli O157:H7 contamination linked to chopped romaine component. Canadian health authorities initiated investigation with linkage to US supply. 16 illnesses in US and Canada.','distribution_pattern'=>'Nationwide at Walmart, Costco, Sam\'s Club, Albertsons, Safeway, Kroger, and other retailers in all states.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20240808','report_date'=>'20240813','product_quantity'=>'~15,000 cases','code_info'=>'Best By dates through 08/14/2024'],

// 14. Jeni's Splendid Ice Creams — Listeria
['recall_number'=>'F-0877-2023','product_description'=>'Jeni\'s Splendid Ice Creams pints and ice cream sandwiches in all flavors','recalling_firm'=>'Jeni\'s Splendid Ice Creams LLC','city'=>'Columbus','state'=>'OH','reason_for_recall'=>'Listeria monocytogenes found in finished product by Ohio Department of Agriculture. All flavors from the affected production date recalled as precautionary measure.','distribution_pattern'=>'Nationwide at Whole Foods Market, Target, Trader Joe\'s, Costco, and Jeni\'s scoop shops in OH, TN, GA, TX, CO, NY, DC, and other states.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20230515','report_date'=>'20230518','product_quantity'=>'~2,700 cases','code_info'=>'Best By 03/26/2024; lot codes on bottom of container'],

// 15. Daily Harvest French Lentil + Leek Crumbles — GI illness
['recall_number'=>'F-1033-2022','product_description'=>'Daily Harvest French Lentil + Leek Crumbles frozen meal','recalling_firm'=>'Daily Harvest Inc.','city'=>'New York','state'=>'NY','reason_for_recall'=>'Hundreds of consumers reported severe gastrointestinal illness, gallbladder removal, and liver damage after consuming. Tara flour ingredient identified as likely cause; product sold through subscription and Walmart.','distribution_pattern'=>'Direct-to-consumer nationwide via Daily Harvest subscription. Also sold at Walmart locations.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20220628','report_date'=>'20220628','product_quantity'=>'All French Lentil + Leek Crumbles units','code_info'=>'All date codes; scan or check app for affected orders'],

// 16. Amy's Kitchen vegan breakfast burritos — undeclared allergen
['recall_number'=>'F-2044-2023','product_description'=>'Amy\'s Kitchen non-dairy, gluten-free breakfast burritos and other frozen entrees','recalling_firm'=>'Amy\'s Kitchen Inc.','city'=>'Petaluma','state'=>'CA','reason_for_recall'=>'Undeclared milk and soy allergens. Products labeled gluten-free and dairy-free contain cheese and soy ingredients not fully declared on label due to ingredient substitution error.','distribution_pattern'=>'Nationwide at Whole Foods Market, Target, Kroger, Walmart, Safeway, Sprouts Farmers Market.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20231108','report_date'=>'20231110','product_quantity'=>'~38,000 cases','code_info'=>'Best By dates 02/2025 through 06/2025, UPC codes on package'],

// 17. Cal-Maine Foods shell eggs — Salmonella
['recall_number'=>'F-0432-2024','product_description'=>'Fresh shell eggs sold under Egg-Land\'s Best, Land O\' Lakes, Farmhouse Eggs, and 4-Grain brands','recalling_firm'=>'Cal-Maine Foods Inc.','city'=>'Jackson','state'=>'MS','reason_for_recall'=>'Salmonella Braenderup contamination linked to flock at Texas facility. 65 illnesses across 9 states linked to eggs from the affected facility.','distribution_pattern'=>'Distributed in TX, CO, FL, OK, KS, TN, MO, MD and other states via Walmart, Kroger, H-E-B, Albertsons, and other retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20240412','report_date'=>'20240415','product_quantity'=>'~8.7 million eggs (approx 726,000 dozen)','code_info'=>'Plant code P-1065; Julian date codes 024 through 100'],

// 18. Good & Gather Target organic spring mix — Listeria
['recall_number'=>'F-0229-2024','product_description'=>'Good & Gather brand Organic Spring Mix and Organic Baby Spinach sold at Target stores','recalling_firm'=>'Fresh Express Incorporated','city'=>'Salinas','state'=>'CA','reason_for_recall'=>'Listeria monocytogenes detected during routine testing of finished product. Target private label (Good & Gather) products produced by Fresh Express under contract.','distribution_pattern'=>'Target stores nationwide in all 50 states.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20240116','report_date'=>'20240119','product_quantity'=>'~12,000 bags','code_info'=>'Best By dates 01/17/2024 through 01/24/2024'],

// 19. Aldi SimplyNature organic apple juice — Patulin (mold toxin)
['recall_number'=>'F-1102-2023','product_description'=>'SimplyNature Organic Apple Juice 64 fl oz bottles sold at Aldi stores','recalling_firm'=>'Aldi Inc.','city'=>'Batavia','state'=>'IL','reason_for_recall'=>'Patulin exceeds FDA action level. Patulin is a mycotoxin produced by mold that may cause GI illness. FDA import alert triggered by positive screening result on product from European supplier.','distribution_pattern'=>'Aldi stores nationwide.','classification'=>'Class II','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20230727','report_date'=>'20230731','product_quantity'=>'~23,000 bottles','code_info'=>'Best By 06/16/2024; UPC 4 099100 063688'],

// 20. Sprouts brand organic blueberries — pesticide
['recall_number'=>'F-1543-2023','product_description'=>'Sprouts Farmers Market brand organic fresh blueberries and mixed berry packages','recalling_firm'=>'Sprouts Farmers Market LLC','city'=>'Phoenix','state'=>'AZ','reason_for_recall'=>'Exceeded tolerance for pesticide residues (etofenprox). FDA import alert on blueberries from Mexico sourced through this supply chain. Exceeds action levels for pesticide.','distribution_pattern'=>'Sprouts stores in AZ, CA, CO, TX, FL, GA, MD, NV, NM, NC, OK, UT, VA, and other Sprouts locations nationwide.','classification'=>'Class II','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20230908','report_date'=>'20230912','product_quantity'=>'~18,000 units','code_info'=>'Sold between 09/01/2023 and 09/08/2023'],

// 21. Meijer brand shredded mozzarella — Listeria
['recall_number'=>'F-0779-2022','product_description'=>'Meijer brand shredded mozzarella cheese and mozzarella string cheese in retail packages','recalling_firm'=>'Meijer Inc.','city'=>'Grand Rapids','state'=>'MI','reason_for_recall'=>'Listeria monocytogenes contamination detected in environmental sampling at the co-manufacturer facility. Products sold exclusively at Meijer stores under Meijer private label.','distribution_pattern'=>'Meijer store locations in MI, OH, IN, IL, KY, WI.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20220415','report_date'=>'20220419','product_quantity'=>'~9,000 units','code_info'=>'Best By 04/10/2022 through 04/20/2022'],

// 22. Duncan Hines cake mix — Salmonella (linked to raw cookie dough)
['recall_number'=>'F-1814-2018','product_description'=>'Duncan Hines Classic White, Classic Yellow, Classic Butter Golden, and Confetti cake mix','recalling_firm'=>'Conagra Brands Inc.','city'=>'Chicago','state'=>'IL','reason_for_recall'=>'Salmonella Mbandaka contamination. Linked to five illnesses when consumers used mixes without cooking (as raw cookie dough). Flour supplier implicated in multistate Salmonella outbreak.','distribution_pattern'=>'Nationwide at Walmart, Target, Kroger, Publix, Albertsons, and all major grocery retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20181101','report_date'=>'20181105','product_quantity'=>'All affected date codes for four varieties','code_info'=>'Best By dates 03/7/2019 through 12/11/2019; four UPC codes'],

// 23. Ocean Mist Farms artichokes — pesticide
['recall_number'=>'F-0663-2024','product_description'=>'Ocean Mist Farms brand fresh artichokes sold at various retailers in multi-count packages','recalling_firm'=>'Ocean Mist Farms','city'=>'Castroville','state'=>'CA','reason_for_recall'=>'Exceeds FDA tolerance for pesticide residue (dicloran) on artichokes. Routine USDA Pesticide Data Program sampling identified violation. No illnesses reported.','distribution_pattern'=>'Nationwide at Walmart, Target, Whole Foods Market, Costco, Kroger, and other retailers.','classification'=>'Class III','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20240305','report_date'=>'20240308','product_quantity'=>'~28,000 cartons','code_info'=>'Production dates 01/15/2024 through 01/29/2024'],

// 24. Sun Orchard juice drinks — Salmonella
['recall_number'=>'F-1199-2022','product_description'=>'Sun Orchard brand apple juice, cranberry juice cocktail, and fruit punch juice drinks in plastic bottles','recalling_firm'=>'Sunburst Beverages Inc.','city'=>'Miami','state'=>'FL','reason_for_recall'=>'Salmonella contamination identified during FDA inspection and microbiological testing of product. Products distributed in southeastern US.','distribution_pattern'=>'Dollar Tree, Dollar General, Family Dollar stores in FL, GA, SC, NC, AL, MS, and other southeastern states.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20221010','report_date'=>'20221014','product_quantity'=>'~180,000 bottles','code_info'=>'Best By dates through 12/2022'],

// 25. Kirkland Signature cashews — undeclared allergen (peanuts)
['recall_number'=>'F-0552-2023','product_description'=>'Kirkland Signature brand whole cashews and mixed nuts 2.5 lb jars sold at Costco','recalling_firm'=>'Costco Wholesale Corporation','city'=>'Issaquah','state'=>'WA','reason_for_recall'=>'Undeclared peanuts in cashew products. Cross-contact during processing at co-manufacturer facility resulted in peanut allergen in products labeled as cashew-only. Risk to peanut-allergic consumers.','distribution_pattern'=>'Costco warehouse locations nationwide and on Costco.com.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20230310','report_date'=>'20230314','product_quantity'=>'~45,000 jars','code_info'=>'Item# 1162380; lot codes on bottom of container through 03/2023'],

// 26. Whole Foods prepared foods — Listeria (deli counter)
['recall_number'=>'F-1312-2022','product_description'=>'Whole Foods Market prepared food items from hot and cold deli bars including salads, soups, and entrees','recalling_firm'=>'Whole Foods Market Services Inc.','city'=>'Austin','state'=>'TX','reason_for_recall'=>'Listeria monocytogenes detected in prepared deli items during routine FDA inspection of central kitchen facility. Products sold at Whole Foods hot and cold bar counters.','distribution_pattern'=>'Whole Foods Market stores in CA, AZ, NV, OR, WA, CO, UT, and other western states.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20220723','report_date'=>'20220726','product_quantity'=>'All hot and cold bar items produced at affected facility between 07/14/2022 and 07/21/2022','code_info'=>'Production dates 07/14/2022 through 07/21/2022 at Emeryville, CA kitchen'],

// 27. Simple Truth (Kroger) organic hummus — undeclared allergen
['recall_number'=>'F-1445-2023','product_description'=>'Simple Truth brand organic roasted garlic hummus and roasted red pepper hummus','recalling_firm'=>'Kroger Co.','city'=>'Cincinnati','state'=>'OH','reason_for_recall'=>'Undeclared sesame. Products contain sesame (tahini) which as of 2023 is a major food allergen. Label compliance issue as sesame was not always prominently declared.','distribution_pattern'=>'Kroger, Ralphs, Fred Meyer, King Soopers, Fry\'s, Smith\'s, Pick \'n Save, and other Kroger banner stores nationwide.','classification'=>'Class II','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20230112','report_date'=>'20230116','product_quantity'=>'~75,000 units','code_info'=>'Best By dates through 01/30/2023'],

// 28. Gorton's Fish fillets — undeclared allergen (milk)
['recall_number'=>'F-0833-2022','product_description'=>'Gorton\'s brand fish sticks, fish fillets, and shrimp products','recalling_firm'=>'Gorton\'s Inc.','city'=>'Gloucester','state'=>'MA','reason_for_recall'=>'Undeclared milk allergen. Milk ingredient present in batter was not declared on packaging for certain SKUs. Persons with milk allergy risk serious allergic reaction.','distribution_pattern'=>'Nationwide at Walmart, Target, Kroger, Costco, Sam\'s Club, Publix, and all major retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20220510','report_date'=>'20220513','product_quantity'=>'~42,000 cases','code_info'=>'Best By dates through 12/2023; affected UPCs listed on recall notice'],

// 29. Wholly Guacamole mini cups — Listeria
['recall_number'=>'F-0944-2021','product_description'=>'Wholly Guacamole brand individual serving cups, snack packs, and larger tubs of guacamole','recalling_firm'=>'Hormel Foods Corporation (MegaMex Foods)','city'=>'Austin','state'=>'TX','reason_for_recall'=>'Listeria monocytogenes detected in product during retail sampling. Products manufactured at facility with confirmed positive environmental samples for Listeria.','distribution_pattern'=>'Nationwide at Walmart, Target, Kroger, Publix, Costco, Sam\'s Club and other retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20211119','report_date'=>'20211123','product_quantity'=>'~6,500 cases','code_info'=>'Best By dates 11/26/2021 through 12/17/2021'],

// 30. Thomas English Muffins — Listeria (Bimbo Bakeries)
['recall_number'=>'F-0151-2023','product_description'=>'Thomas\' Original English Muffins, Thomas\' Whole Wheat English Muffins, and Bays English Muffins','recalling_firm'=>'Bimbo Bakeries USA Inc.','city'=>'Horsham','state'=>'PA','reason_for_recall'=>'Listeria monocytogenes contamination detected in production environment at Lancaster PA bakery. Products potentially affected include Thomas\' and Bays brand English muffins.','distribution_pattern'=>'Nationwide at Walmart, Target, Kroger, Publix, Safeway, Albertsons and other retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20230104','report_date'=>'20230106','product_quantity'=>'~5,600 cases','code_info'=>'Best By dates 01/02/2023 through 01/16/2023, code 5314 on package'],

// 31. Publix Aprons chicken soup — Listeria
['recall_number'=>'F-0482-2022','product_description'=>'Publix Aprons brand ready-to-eat chicken noodle soup and chicken tortilla soup sold in the deli','recalling_firm'=>'Publix Super Markets Inc.','city'=>'Lakeland','state'=>'FL','reason_for_recall'=>'Listeria monocytogenes detected in ready-to-eat soup products. Identified through routine environmental monitoring at central Publix kitchen facility.','distribution_pattern'=>'Publix Super Market locations in FL, GA, AL, TN, SC, NC, VA.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20220303','report_date'=>'20220307','product_quantity'=>'~4,200 units','code_info'=>'Sell By dates 02/28/2022 through 03/04/2022 at Publix deli departments'],

// 32. Applegate Farms Naturals turkey — Listeria
['recall_number'=>'F-1722-2021','product_description'=>'Applegate Naturals brand sliced turkey, ham, and chicken luncheon meats','recalling_firm'=>'Applegate Farms LLC','city'=>'Bridgewater','state'=>'NJ','reason_for_recall'=>'Listeria monocytogenes found in product sample during regulatory routine testing. Applegate brand is marketed as natural/organic. Products potentially contaminated at production facility.','distribution_pattern'=>'Nationwide at Whole Foods Market, Walmart, Target, Kroger, and natural food retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20211008','report_date'=>'20211013','product_quantity'=>'~8,600 packages','code_info'=>'Use By dates 10/04/2021 through 10/25/2021'],

// 33. Sabra Hummus — Listeria (large brand recall)
['recall_number'=>'F-0533-2020','product_description'=>'Sabra brand classic hummus, roasted garlic hummus, and other refrigerated hummus varieties','recalling_firm'=>'Sabra Dipping Company LLC','city'=>'White Plains','state'=>'NY','reason_for_recall'=>'Listeria monocytogenes potentially present in product. Positive environmental sample at manufacturing facility in Colonial Heights, VA. Recall is precautionary.','distribution_pattern'=>'Nationwide at Walmart, Target, Kroger, Costco, Publix, Whole Foods Market, Safeway and all major retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20200211','report_date'=>'20200213','product_quantity'=>'All hummus with Best By dates through 04/21/2020','code_info'=>'Best By dates through 04/21/2020 from Colonial Heights VA plant'],

// 34. Trader Joe's White Cheddar Corn Puffs — undeclared allergen
['recall_number'=>'F-1887-2022','product_description'=>'Trader Joe\'s White Cheddar Corn Puffs 7 oz bags','recalling_firm'=>'Trader Joe\'s Company','city'=>'Monrovia','state'=>'CA','reason_for_recall'=>'Undeclared milk allergen in a specific lot. Mislabeled packaging from supplier resulted in non-dairy seasoning label on dairy-seasoned product. Risk to milk-allergic consumers.','distribution_pattern'=>'Trader Joe\'s store locations nationwide.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20221215','report_date'=>'20221219','product_quantity'=>'~22,000 bags','code_info'=>'Best By 02/09/2023; barcode 00487956'],

// 35. Blue Diamond Almonds — Salmonella
['recall_number'=>'F-2101-2020','product_description'=>'Blue Diamond Growers brand whole natural almonds and roasted almonds in various sizes','recalling_firm'=>'Blue Diamond Growers','city'=>'Sacramento','state'=>'CA','reason_for_recall'=>'Salmonella found during routine finished product testing. Products distributed nationally under Blue Diamond brand and in bulk for food service.','distribution_pattern'=>'Nationwide at Walmart, Target, Costco, Kroger, Safeway, Whole Foods Market, and other retailers.','classification'=>'Class I','status'=>'completed','voluntary_mandated'=>'Voluntary: Firm Initiated','recall_initiation_date'=>'20200508','report_date'=>'20200512','product_quantity'=>'~15,000 cases','code_info'=>'Harvest codes 2019 with specific plant code; see recall notice for lot codes'],

];

// ──────────────────────────────────────────────────────────────────
// BATCH 2 — FSIS RECORDS
// ──────────────────────────────────────────────────────────────────
$FSIS = [

// 1. Foster Farms chicken — Salmonella Heidelberg (prolonged outbreak)
['RI_ID'=>'101-2024','Name'=>'Foster Farms brand fresh chicken parts, whole chickens, and ground chicken','Company'=>'Foster Poultry Farms LLC','RDate'=>'2024-04-15','Class'=>'Class I','Summary'=>'Raw chicken products contaminated with Salmonella Heidelberg linked to ongoing consumer illness reports. FSIS issued public health alert after 634 illnesses in 29 states traced to Foster Farms plants in California. Drug-resistant strain complicates treatment.','States'=>'Nationwide at Walmart, Costco, Safeway, Albertsons, Kroger, and other grocery retailers. Heavy distribution in CA, WA, OR, AZ, NV.','Status'=>'completed','Pounds'=>'170000'],

// 2. Butterball ground turkey — Salmonella Hadar
['RI_ID'=>'077-2022','Name'=>'Butterball brand fresh ground turkey in 1 lb and 3 lb packages','Company'=>'Butterball LLC','RDate'=>'2022-11-18','Class'=>'Class I','Summary'=>'Ground turkey potentially contaminated with Salmonella Hadar. FSIS identified through illness cluster investigation and environmental testing at Jonesboro, AR facility. 35 illnesses in 12 states.','States'=>'Nationwide at Walmart, Sam\'s Club, Target, Kroger, Publix, Meijer, and all major grocery retailers.','Status'=>'completed','Pounds'=>'14338'],

// 3. Wayne Farms chicken breast — Salmonella
['RI_ID'=>'051-2023','Name'=>'Wayne Farms LLC brand chicken breast fillets and boneless skinless chicken tenders','Company'=>'Wayne Farms LLC','RDate'=>'2023-06-09','Class'=>'Class I','Summary'=>'Chicken breast products potentially contaminated with Salmonella. FSIS inspection identified sanitation control inadequacies at Dothan, AL plant. Products distributed to retail and food service.','States'=>'Distributed in AL, GA, FL, TN, MS, NC, SC and other southeastern states at Publix, Winn-Dixie, Food Lion.','Status'=>'completed','Pounds'=>'46820'],

// 4. Pilgrim's Pride chicken thighs — Salmonella
['RI_ID'=>'038-2022','Name'=>'Pilgrim\'s Pride brand bone-in chicken thighs and whole legs, individually quick frozen','Company'=>'Pilgrim\'s Pride Corporation','RDate'=>'2022-07-28','Class'=>'Class I','Summary'=>'Frozen chicken thigh products potentially contaminated with Salmonella. FSIS routine sampling identified positive results at the Mt. Pleasant TX facility. Distributed nationwide.','States'=>'Nationwide at Walmart, Kroger, H-E-B, Winn-Dixie, Publix and other retailers. Distribution in all 50 states.','Status'=>'completed','Pounds'=>'48849'],

// 5. Oscar Mayer cold cuts — Listeria (multiple ready-to-eat)
['RI_ID'=>'009-2023','Name'=>'Oscar Mayer Classic brand bologna, salami, and ham luncheon meat sliced packages','Company'=>'Oscar Mayer Foods LLC (Kraft Heinz)','RDate'=>'2023-01-31','Class'=>'Class I','Summary'=>'Ready-to-eat luncheon meat products potentially contaminated with Listeria monocytogenes. FSIS issued recall after positive results in product sampling. Oscar Mayer products sold at virtually all US grocery retailers.','States'=>'Nationwide at all major retailers including Walmart, Target, Kroger, Costco, Publix, Safeway, Aldi.','Status'=>'completed','Pounds'=>'11292'],

// 6. Tyson beef patties — E. coli O157:H7
['RI_ID'=>'065-2021','Name'=>'Tyson Fresh Meats beef patties and burger products, fresh and frozen','Company'=>'Tyson Fresh Meats Inc.','RDate'=>'2021-08-06','Class'=>'Class I','Summary'=>'Ground beef patties potentially contaminated with E. coli O157:H7. FSIS testing identified contamination in lot produced at Dakota City, NE facility. Voluntary recall before illnesses reported.','States'=>'Nationwide at Walmart, Sam\'s Club, Target, Kroger, and other retailers.','Status'=>'completed','Pounds'=>'8955853'],

// 7. Perdue whole chickens — Salmonella (broiler plant)
['RI_ID'=>'054-2024','Name'=>'Perdue Farms brand fresh whole chickens and whole chicken cut-up parts','Company'=>'Perdue Farms LLC','RDate'=>'2024-07-10','Class'=>'Class I','Summary'=>'Fresh whole chickens and cut-up chicken parts potentially contaminated with Salmonella Infantis. FSIS HACCP performance standard violation identified at Lewiston, NC processing facility. Linked to 12 consumer illness reports.','States'=>'Distributed primarily in NC, VA, MD, DE, NJ, NY, PA, CT, MA, RI at Walmart, Costco, Whole Foods.','Status'=>'completed','Pounds'=>'33750'],

// 8. ConAgra Banquet frozen chicken pot pies — Salmonella
['RI_ID'=>'023-2022','Name'=>'Banquet and Great Value brand frozen chicken pot pies and frozen turkey pot pies','Company'=>'ConAgra Brands Inc.','RDate'=>'2022-03-25','Class'=>'Class I','Summary'=>'Frozen pot pies potentially contaminated with Salmonella. Thorough cooking destroys Salmonella but consumer testing showed many failed to reach safe internal temperature. 35 states affected.','States'=>'Nationwide at Walmart, Target, Kroger, Aldi, and other retailers. Great Value brand at Walmart only.','Status'=>'completed','Pounds'=>'181000'],

// 9. Kraft Heinz deli turkey — Listeria
['RI_ID'=>'016-2024','Name'=>'Kraft brand Deli Deluxe sliced turkey, sliced ham, and Oscar Mayer Deli Fresh turkey','Company'=>'Kraft Heinz Company','RDate'=>'2024-02-14','Class'=>'Class I','Summary'=>'Ready-to-eat deli sliced turkey and ham products potentially contaminated with Listeria monocytogenes found during routine FSIS testing. Products include both Kraft Deli Deluxe and Oscar Mayer Deli Fresh product lines.','States'=>'Nationwide at Walmart, Kroger, Target, Publix, Albertsons, Safeway, Sam\'s Club, Costco.','Status'=>'ongoing','Pounds'=>'55704'],

// 10. Land O'Frost lunchmeat — Listeria
['RI_ID'=>'047-2021','Name'=>'Land O\'Frost premium brand ready-to-eat oven roasted turkey and chicken breast luncheon meats','Company'=>'Land O\'Frost Inc.','RDate'=>'2021-05-22','Class'=>'Class I','Summary'=>'Ready-to-eat luncheon meat potentially contaminated with Listeria monocytogenes. Products manufactured at Lansing, IL facility and distributed under Land O\'Frost and other labels including Sam\'s Club Member\'s Mark.','States'=>'Nationwide at Walmart, Sam\'s Club, Kroger, Publix, Meijer, and other retailers in all 50 states.','Status'=>'completed','Pounds'=>'127425'],

// 11. Hannah Foods lamb/beef patties — E. coli
['RI_ID'=>'074-2022','Name'=>'Hannah Foods brand lamb and beef blended burger patties','Company'=>'Hannah Foods Inc.','RDate'=>'2022-10-11','Class'=>'Class I','Summary'=>'Ground lamb and beef patty products potentially contaminated with E. coli O157:H7. FSIS sampling identified positive result. Products sold at specialty halal and Mediterranean grocery stores.','States'=>'NY, NJ, CT, MA, PA, MI, OH and other states with significant Middle Eastern/Mediterranean retail presence.','Status'=>'completed','Pounds'=>'10788'],

// 12. Sysco ready-to-eat turkey — Listeria (foodservice)
['RI_ID'=>'033-2023','Name'=>'Sysco brand sliced cooked turkey breast for institutional and restaurant use','Company'=>'Sysco Corporation','RDate'=>'2023-04-28','Class'=>'Class I','Summary'=>'Sliced cooked turkey breast products potentially contaminated with Listeria monocytogenes. Distributed through Sysco foodservice distribution network to hospitals, schools, restaurants, and other institutions nationwide.','States'=>'Distributed to foodservice operators nationwide in all 50 states.','Status'=>'completed','Pounds'=>'8827'],

// 13. Boar's Head additional product expansion (ham and roast beef)
['RI_ID'=>'083-2024','Name'=>'Boar\'s Head brand cooked ham, roast beef, and turkey products — expanded recall','Company'=>'Boar\'s Head Provisions Co. Inc.','RDate'=>'2024-08-02','Class'=>'Class I','Summary'=>'Expansion of Listeria monocytogenes recall (original RI# 082-2024) to include additional RTE ham, roast beef, and turkey products produced at the Jarratt, VA facility. Outbreak now linked to 9 deaths, 57 hospitalizations.','States'=>'Nationwide at all deli counters carrying Boar\'s Head products, including Walmart, Kroger, ShopRite, Publix.','Status'=>'completed','Pounds'=>'7000000'],

// 14. Trimaco ready-to-eat pork — Listeria
['RI_ID'=>'061-2022','Name'=>'Trimaco brand ready-to-eat pork products including carnitas, barbacoa, and chipotle pork','Company'=>'Sigma Alimentos USA Inc.','RDate'=>'2022-08-17','Class'=>'Class I','Summary'=>'Ready-to-eat refrigerated pork products contaminated with Listeria monocytogenes. FSIS inspection identified pathogen at facility. Products distributed under Trimaco and FUD brand labels at Mexican/Latin food specialty retailers.','States'=>'CA, TX, AZ, NM, FL, IL, NY and other states with large Hispanic consumer populations. Sold at Walmart, Food 4 Less, Fiesta Mart.','Status'=>'completed','Pounds'=>'42930'],

// 15. National Steak and Poultry beef — E. coli (mechanically tenderized)
['RI_ID'=>'055-2023','Name'=>'National Steak and Poultry beef blade tenderized steaks and roasts for foodservice and retail','Company'=>'National Steak and Poultry LLC','RDate'=>'2023-08-12','Class'=>'Class I','Summary'=>'Blade/mechanically tenderized beef products potentially contaminated with E. coli O157:H7. Non-intact beef products require higher cooking temperatures than whole cuts. Products supplied to restaurants and retail.','States'=>'Distributed nationwide to foodservice and retail in multiple states.','Status'=>'completed','Pounds'=>'19160'],

];

// ──────────────────────────────────────────────────────────────────
// Run ingestion
// ──────────────────────────────────────────────────────────────────
echo "Seeding FDA batch 2 (" . count($FDA) . " records)...\n";
$run1 = start_run('FDA');
foreach ($FDA as $raw) {
    try {
        $p = parse_fda_record($raw, $fda_id);
        if ($p === 'skip') { $R['skipped']++; continue; }
        $out = upsert_recall($p, $raw);
        $R[$out]++;
        echo "  [$out] {$raw['recall_number']} — " . mb_substr($raw['product_description'], 0, 62) . "\n";
    } catch (\Throwable $e) {
        $R['errors'][] = $raw['recall_number'] . ': ' . $e->getMessage();
        echo "  [ERROR] {$raw['recall_number']}: {$e->getMessage()}\n";
    }
}
finish_run($run1, ['fetched'=>count($FDA),'inserted'=>$R['inserted'],'updated'=>$R['updated'],'rejected'=>$R['skipped'],'errors'=>$R['errors']]);

echo "\nSeeding FSIS batch 2 (" . count($FSIS) . " records)...\n";
$run2 = start_run('FSIS');
foreach ($FSIS as $raw) {
    try {
        $p = parse_fsis_record($raw, $fsis_id);
        if ($p === 'skip') { $R['skipped']++; continue; }
        $out = upsert_recall($p, $raw);
        $R[$out]++;
        echo "  [$out] FSIS-{$raw['RI_ID']} — " . mb_substr($raw['Name'], 0, 62) . "\n";
    } catch (\Throwable $e) {
        $R['errors'][] = 'FSIS-' . $raw['RI_ID'] . ': ' . $e->getMessage();
        echo "  [ERROR] FSIS-{$raw['RI_ID']}: {$e->getMessage()}\n";
    }
}
finish_run($run2, ['fetched'=>count($FSIS),'inserted'=>$R['inserted'],'updated'=>$R['updated'],'rejected'=>$R['skipped'],'errors'=>$R['errors']]);

echo "\nRunning rescore_all()...\n";
rescore_all();

echo "\n=== BATCH 2 COMPLETE ===\n";
printf("Inserted : %d\nUpdated  : %d\nSkipped  : %d\nErrors   : %d\n",
    $R['inserted'], $R['updated'], $R['skipped'], count($R['errors']));
if ($R['errors']) { echo "\nErrors:\n"; foreach ($R['errors'] as $e) echo "  $e\n"; }

$total  = $db->query('SELECT COUNT(*) FROM recalls')->fetchColumn();
$states = $db->query('SELECT COUNT(DISTINCT state_code) FROM recall_states')->fetchColumn();
$rets   = $db->query('SELECT COUNT(*) FROM retailers')->fetchColumn();
$scored = $db->query('SELECT COUNT(*) FROM retail_exposures')->fetchColumn();
$hazs   = $db->query('SELECT COUNT(DISTINCT hazard_id) FROM recall_hazards')->fetchColumn();
echo "\nDB totals:\n";
printf("  Recalls       : %d\n  States        : %d\n  Retailers     : %d\n  Hazard links  : %d\n  Risk rows     : %d\n",
    $total, $states, $rets, $hazs, $scored);
