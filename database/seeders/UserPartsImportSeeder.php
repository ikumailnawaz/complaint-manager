<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\MachineModel;
use App\Models\Part;
use App\Models\PartStockLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserPartsImportSeeder extends Seeder
{
    public function run(): void
    {
        // Raw dataset provided by user
        $rawTsv = <<<'TSV'
BM350-0022	Assembly Unit	BM-350	0
BM350-0014	auto sensor	BM-350	0
BM350-0017	Bearing	BM-350	0
BM350-0007	Came	BM-350	0
BM350-0008	Came shaft	BM-350	0
BM350-0002	cutter	BM-350	35
BM350-0005	Display	BM-350	10
BM350-0027	Display Cable	BM-350	0
BM350-0025	Feeding Motor	BM-350	29
BM350-0012	feeding rollers	BM-350	0
BM350-0015	feeding sensor	BM-350	0
BM350-0001	Heater 220v	BM-350	20
BM350-0024	Heater 38v	BM-350	32
BM350-0023	Heater Holder	BM-350	0
BM350-0004	Main Controller IC	BM-350	50
BM350-0018	Main motor	BM-350	0
BM350-0003	Main pcb	BM-350	38
BM350-0013	paper clamp	BM-350	0
BM350-0021	Paper Holder Gear	BM-350	0
BM350-0011	paper path	BM-350	15
BM350-0010	Paper Roller	BM-350	0
BM350-0006	power supply	BM-350	2
BM350-0028	Sensor Cable	BM-350	0
BM350-0026	Spring	BM-350	60
BM350-0009	Stopper plate	BM-350	4
BM350-0019	Transformer	BM-350	0
BM350-0029	U Disk For sensor	BM-350	0
BM350-0016	u-sensor	BM-350	0
20250917	Feeding Motor	Canny	10
20251020	Lever Motor	Chihua	48
RES --CM25v1	MIAN PCB	Chihua	1
CMS30MM_305_0012	Auto sensor	CMS_30MM	0
CMS30MM_303_0009	Auto Sensor	CMS_30MM	0
CMS30MM_305_0003	clatch wire	CMS_30MM	0
CMS30MM_305_0013	Cutter	CMS_30MM	40
CMS30MM_303_0010	Cutter	CMS_30MM	0
CMS30MM_305_0011	Display	CMS_30MM	4
CMS30MM_304_0002	Display	CMS_30MM	0
CMS30MM_303_0008	Display	CMS_30MM	0
CMS30MM_305_0010	Display Push Button	CMS_30MM	0
CMS30MM_305_0008	Feeding motor	CMS_30MM	3
CMS30MM_305_0007	Feeding motor plate	CMS_30MM	41
CMS30MM_303_0005	Feeding motor plate	CMS_30MM	0
CMS30MM_305_0005	feeding roller green	CMS_30MM	64
CMS30MM_303_0003	feeding roller green	CMS_30MM	0
CMS30MM_303_0004	feeding roller White	CMS_30MM	0
CMS30MM_305_0015	Feeding sensor	CMS_30MM	0
CMS30MM_305_0004	feeding White roller	CMS_30MM	43
CMS30MM_305_0002	Gear	CMS_30MM	0
CMS30MM_303_0002	Gear	CMS_30MM	0
CMS30MM_305_0001	Heater	CMS_30MM	53
CMS30MM_303_0001	Heater	CMS_30MM	0
CMS30MM_305_0009	Main board	CMS_30MM	3
CMS30MM_304_0003	Main Board	CMS_30MM	7
CMS30MM_303_0007	Main Board	CMS_30MM	22
Auto.00.002	main motor	CMS_30MM	8
CMS30MM_305_0014	Paper path	CMS_30MM	0
CMS30MM_303_0011	Paper path	CMS_30MM	0
CMS30MM_305_0018	Paper path back	CMS_30MM	0
CMS30MM_303_0012	Paper path back	CMS_30MM	0
CMS30MM_303_0013	Stopper	CMS_30MM	0
CMS30MM_305_0016	Stopper Plate	CMS_30MM	0
CMS30MM_305_0017	Transformer	CMS_30MM	0
CMS30MM_304_0001	Transformer	CMS_30MM	0
04.01.12211*1	IR Sensor	GA-QFJ 3201	16
Auto_00131	CIS SCANNER	GA-QFJ 3201	1
04.01.12212*1	IR Receiver	GA-QFJ 3201	12
00.00.0601685*	28 pin Lower Cable	GA-QFJ 3201	23
00.00.0601686*	28 pin upper Cable	GA-QFJ 3201	35
00.00.0600024*	8 pin Lower Cable	GA-QFJ 3201	11
00.00.6624*	8 pin upper Cable	GA-QFJ 3201	10
04.01.02100141	Display Panel	GA-QFJ 3201	7
04.00.0000154	Display Pocket	GA-QFJ 3201	6
02.03.12019	feeding first Shaft Rubber	GA-QFJ 3201	76
01.10.1000033	Feeding Motor Plate	GA-QFJ 3201	3
04.01.2800236	Feeding Roller	GA-QFJ 3201	17
Auto_0016	Feeding shaft 01 Roller pcs with shafts	GA-QFJ 3201	8
04.00.00160	Hopper Sensor	GA-QFJ 3201	15
04.00.2000211	Image Board 1	GA-QFJ 3201	3
00.00.0601659	LCD cables	GA-QFJ 3201	26
04.00.0100092	Mian  Board	GA-QFJ 3201	8
04.00.06000023	Motor Control Board	GA-QFJ 3201	4
Auto_0017	Reverse Roller	GA-QFJ 3201	6
Auto_0018	reverse Roller Holder	GA-QFJ 3201	5
01.01.2800223	Shaft first Feeding Roller	GA-QFJ 3201	92
01.04.0000127	Solenoid	GA-QFJ 3201	32
04.01.12104	Transport Upper bearing	GA-QFJ 3201	20
00.00.0601685	28 pin Lower Cable	GA-QFJ 4300	37
00.00.0601686	28 pin upper Cable	GA-QFJ 4300	37
00.00.0600024	8 pin Lower Cable	GA-QFJ 4300	22
00.00.6624	8 pin upper Cable	GA-QFJ 4300	16
04.01.12180	Bearing Arm	GA-QFJ 4300	16
Auto_0010	Bill Holder	GA-QFJ 4300	10
00.00.061663	BV Unit cable	GA-QFJ 4300	5
04.02.0400017	BV Unit Long Belt 290.S2M	GA-QFJ 4300	14
04.01.1200031	BV Unit Shaft No.1	GA-QFJ 4300	10
04.01.1200043	BV Unit shaft no.2	GA-QFJ 4300	10
04.00.0100031	Control Board	GA-QFJ 4300	11
00.08.01095	Counting Board	GA-QFJ 4300	8
02.03.08030	Double Teeth Belt	GA-QFJ 4300	14
04.00.00079	Driver Motor Board	GA-QFJ 4300	7
Auto no 1	E Type Shaft	GA-QFJ 4300	1
01.10.00001	E Type Unit	GA-QFJ 4300	2
04.01.12214	Encorder Sensor	GA-QFJ 4300	8
Auto_0006	Encorder Sensor+ Magnet	GA-QFJ 4300	20
01.04.00032	Feeding Motor	GA-QFJ 4300	10
02.03.04115	Feeding Roller (shaft (02) Mid rubber	GA-QFJ 4300	67
02.03.19000083	Feeding Roller First shaft	GA-QFJ 4300	58
02.03.1900085	Feeding Roller Shaft (02) Side	GA-QFJ 4300	46
02.03,04224	Feeding Roller Shaft (02) Side rubber	GA-QFJ 4300	130
04.01.1200087	Feeding Roller Shaft 03	GA-QFJ 4300	20
02.03.04031	Feeding rRoller Shaft (02) Mid	GA-QFJ 4300	82
04.00.0000295	Fuse Board	GA-QFJ 4300	2
Auto_0014	Hopper Encorder Sensor	GA-QFJ 4300	10
04.00.200021	Image Board	GA-QFJ 4300	4
Auto_0011	Image Board cell	GA-QFJ 4300	10
04.00.0100086	Image Supply Board	GA-QFJ 4300	6
04.01.12212	IR Receiver	GA-QFJ 4300	20
04.01.12211	IR Sensor	GA-QFJ 4300	20
Auto_0003	Kicker Roller shaft 01 pcs with shafts	GA-QFJ 4300	11
Auto_0012	LCD cables	GA-QFJ 4300	23
Auto_0015	LCD Panel	GA-QFJ 4300	10
Auto_0002	Magnet Sensor	GA-QFJ 4300	5
Auto_0004	MG Plate	GA-QFJ 4300	35
Auto_0005	MG Unit	GA-QFJ 4300	10
04.00.000154	pocket display	GA-QFJ 4300	3
00.00.000086	Power Supply	GA-QFJ 4300	3
R.M.0001	Rejection / Feeding Motor Shaft	GA-QFJ 4300	20
04.01.120024	Rejection Pully shaft	GA-QFJ 4300	34
02.03.04058	Rejection Unit Guide Plate	GA-QFJ 4300	10
12*510*0.65	Rejection Unit Long Belt	GA-QFJ 4300	20
Auto_0009	Rejection Unit Rollers Khol	GA-QFJ 4300	100
04.01.12166	Rejection unit shaft no.4	GA-QFJ 4300	10
04.01.1200085	Reverse Roller	GA-QFJ 4300	7
Auto.00.001	Shaft no 3 Transport Unit	GA-QFJ 4300	0
Auto_0008	Shaft No.5, Rejection unit	GA-QFJ 4300	8
02.02.04034	Solenoid Comb	GA-QFJ 4300	9
Auto_0001	Stacker supply	GA-QFJ 4300	17
Auto_0007	TDS Unit	GA-QFJ 4300	5
04.000.100076	Thinkness board	GA-QFJ 4300	9
12*810*0.65	Transport Long Belt Upper	GA-QFJ 4300	14
04.00.0000327	Transport Motor	GA-QFJ 4300	5
Auto.00.0055	Uthering Bearing	GA-QFJ 4300	158
12*775*0.65	Rejection Long Belt	GA-QFJ-6400	40
12*1096*0.65	Trasnport Long Belt	GA-QFJ-6400	20
04.00.0100036	image Board	GA-QFJ2101A10	1
04.00.02028	LCD	GA-QFJ2101A10	2
04.00.000032	Main Board	GA-QFJ2101A10	1
04.00.0000349	MG Plate	GA-QFJ2101A10	1
04.00.0000328	Motor drives Board	GA-QFJ2101A10	1
00.00.00326	Supply	GA-QFJ2101A10	1
04.00.0000475	USB Port Board	GA-QFJ2101A10	1
12*154*0.65	C Lower	Glory usf-300	48
12*123*0.65	C Upper	Glory usf-300	48
12*156*0.65	D Lower	Glory usf-300	50
12*151*0.65	Gooao D Lower	Glory usf-300	50
12*173*0.65	Gooao D Upper	Glory usf-300	50
12*332*0.65	Pocket 1	Glory usf-300	48
12*256*0.65	Pocket 2/3	Glory usf-300	96
12*895*0.65	Transport Long Belt	Glory usf-300	98
VC-0018	Assembly motor	Vc870	0
VC-0015	auto switch	Vc870	0
VC-0002	Belt	Vc870	0
VC-0022	Body Base	Vc870	7
VC-0016	carbon	Vc870	0
VC-0010	Counting sensor	Vc870	23
VC-0006	Display	Vc870	6
VC-0008	End cape	Vc870	26
VC-0005	Filter	Vc870	2
VC-0007	filter cape	Vc870	0
VC-0020	Gear 1 to 5	Vc870	95
VC-0003	Keypad	Vc870	39
VC-0013	lever	Vc870	0
VC-0017	Lever motor	Vc870	0
VC-0014	Lever switch	Vc870	0
VC-0012	liver set	Vc870	0
VC-0004	Main Board	Vc870	4
VC-0009	Power supply	Vc870	4
VC-0011	pump	Vc870	0
VC-0019	Spindle Bearing	Vc870	0
VC-0001	Spindle Finger	Vc870	6
VC-0021	SSR Relay	Vc870	0
TSV;

        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        // Clear existing demo parts and models
        DB::table('part_stock_ledgers')->truncate();
        DB::table('stock_movements')->truncate();
        DB::table('machine_model_parts')->truncate();
        DB::table('part_request_items')->truncate();
        DB::table('part_requests')->truncate();
        DB::table('grn_items')->truncate();
        DB::table('grns')->truncate();
        DB::table('part_transfer_items')->truncate();
        DB::table('part_transfers')->truncate();
        DB::table('parts')->truncate();
        DB::table('machine_models')->truncate();

        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // Ensure 3 standard locations exist
        $locations = [
            Location::firstOrCreate(['name' => 'Lahore Head Office'], ['type' => 'office', 'city' => 'Lahore', 'is_active' => true]),
            Location::firstOrCreate(['name' => 'Karachi Regional Office'], ['type' => 'office', 'city' => 'Karachi', 'is_active' => true]),
            Location::firstOrCreate(['name' => 'Islamabad Hub'], ['type' => 'hub', 'city' => 'Islamabad', 'is_active' => true]),
        ];

        // Parse lines
        $lines = explode("\n", trim($rawTsv));
        $modelCache = [];
        $partsCreated = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = preg_split('/\t+/', $line);
            if (count($parts) < 3) continue;

            $partCode = trim($parts[0], " \t\n\r\0\x0B\"");
            $partName = trim($parts[1], " \t\n\r\0\x0B\"");
            $modelName = trim($parts[2], " \t\n\r\0\x0B\"");

            if (empty($partCode) || empty($partName) || empty($modelName)) continue;

            // Normalize Model Type
            $machineType = '';
            $lowerModel = strtolower($modelName);
            if (str_contains($lowerModel, 'pos') || str_contains($lowerModel, 'verifone')) {
                $machineType = 'pos';
            } elseif (str_contains($lowerModel, 'cdm') || str_contains($lowerModel, 'deposit')) {
                $machineType = 'cdm';
            } elseif (str_contains($lowerModel, 'kiosk')) {
                $machineType = 'kiosk';
            }

            // Create or retrieve Machine Model
            if (!isset($modelCache[$modelName])) {
                $modelCache[$modelName] = MachineModel::firstOrCreate(
                    ['name' => $modelName],
                    ['manufacturer' => explode(' ', $modelName)[0], 'machine_type' => $machineType, 'is_active' => true]
                );
            }
            $model = $modelCache[$modelName];

            // Create or retrieve Part (unique by part_number)
            $part = Part::firstOrCreate(
                ['part_number' => $partCode],
                [
                    'name' => $partName,
                    'unit' => 'PCS',
                    'unit_cost' => 0.00,
                    'reorder_level' => 0,
                    'is_active' => true,
                ]
            );

            // Attach Part to Machine Model via pivot
            $model->parts()->syncWithoutDetaching([$part->id => ['is_common' => false]]);

            // Seed stock ledger: Initialize all locations at 0 stock
            foreach ($locations as $loc) {
                PartStockLedger::updateOrCreate(
                    ['part_id' => $part->id, 'location_id' => $loc->id],
                    ['qty_on_hand' => 0, 'qty_reserved' => 0]
                );
            }

            $partsCreated++;
        }

        echo "Successfully imported " . count($modelCache) . " machine models and {$partsCreated} parts with 0 initial stock.\n";
    }
}
