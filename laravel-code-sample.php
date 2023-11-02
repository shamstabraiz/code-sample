<?php

namespace App\Http\Controllers;

use App\Component;
use App\Events\ProjectUpdated;
use App\KitList;
use App\KitListStock;
use App\PoHeader;
use App\PoStockOrdered;
use App\Events\KitListRefreshRequested;
use App\PoStockReceived;
use App\Project;
use App\Transformers\PoStockOrderedTransformer;
use App\User;
use App\Warehouse;
use App\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GlobalPOController extends Controller
{
    //
    public function globalPO()
    {
        $request = request();
        $team = $request->get('team');
        $user = \Auth::user();
        $can_global_po = $user->canBrowseGlobalPO($team);
        if (!$can_global_po) {
            return redirect()->route('home_dashboard')->with([
                'flash_message' => 'No Access',
                'flash_message_error' => true,
            ]);
        }

        $projects = Project::all();

        if (isset($projects) && count($projects) > 0) {
            return view('global-po.global-po')->with([
                'projects' => $projects,
            ]);
        } else {
            return redirect()->route('home_dashboard')->with([
                'flash_message' => 'No Projects Created',
                'flash_message_error' => true,
            ]);
        }
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $this->validate($request, [
            'po_code' => 'string',
        ]);

        // $project = Project::findOrFail($project_id);
        $user = \Auth::user();
        $can_manage_projects = $user->canManageProjects($request->get('team'));
        $warehouseStockId = null;
        $componentId = null;
        $user = Auth::user();
        $team = $user->teams->first();
        if ($can_manage_projects) {
            $orders = $request->get('orders');
            foreach ($orders as $order) {
                $warehouse = Warehouse::find($order["warehouse_id"]);
                $projects = Project::where("warehouse_id", $order["warehouse_id"])->get()->pluck('id')->toArray();
                $warehouseStockId = null;
                $componentId = null;

                if ($order['component_name'] == null) {
                    $component = Component::where('name', $order['component_imported_name'])->get();
                    if (empty($component)) {
                        // if ($component->empty()) {
                        $com = Component::create([
                            'team_id' => $team->id,
                            'name' => $order['component_imported_name'],
                            'code' => '',
                            'description' => '',
                        ]);
                        $com->save();
                        if (!empty($com)) {
                            $componentId = $com->id;
                        }
                        // $kitlist = KitList::where('project_id', $project_id)->where('active', '1')->get()->first();
                        $kitlist = KitList::where('active', '1')->whereIn('project_id', $projects)->get();
                        foreach ($kitlist as $value) {
                            $kitlist_stock = KitListStock::where('kit_list_id', $value->id)->where('imported_name', $order['component_imported_name'])->get();
                            foreach ($kitlist_stock as $stock) {
                                $stock->update([
                                    'component_id' => $com->id,
                                ]);
                            }
                        }
                    } else {
                        $warehouse = $warehouse;

                        $comp = Component::where('name', $order['component_imported_name'])?->first();
                        if (!empty($comp)) {
                            $componentId = $comp->id;
                        }
                        //extra code for Component not exist
                        else {
                            $user = Auth::user();
                            $team = $user->teams->first();
                            $comp = Component::create([
                                'team_id' => $team->id,
                                'name' => $order['component_imported_name'],
                                'code' => '',
                                'description' => '',
                            ]);
                            $comp = $com;

                            $componentId = $comp->id;
                        }
                        // $kitlist = KitList::where('project_id', $project_id)->where('active', '1')->get()->first();
                        $kitlist = KitList::where('active', '1')->whereIn('project_id', $projects)->get();
                        foreach ($kitlist as $value) {
                            $kitlist_stock = KitListStock::where('kit_list_id', $value->id)->where('imported_name', $order['component_imported_name'])->get();
                            foreach ($kitlist_stock as $stock) {
                                $stock->update([
                                    'component_id' => $comp->id,
                                ]);
                            }
                        }
                    }
                } else {
                    $warehouse = $warehouse;
                    $comp = Component::where('name', $order['component_name'])?->first();

                    if (!empty($comp)) {
                        $componentId = $comp->id;
                    } else {
                        $com = Component::create([
                            'team_id' => $team->id,
                            'name' => $order['component_name'],
                            'code' => '',
                            'description' => '',
                        ]);
                        $com->save();
                        $comp = $com;
                        $componentId = $com->id;
                    }
                    // $kitlist = KitList::where('project_id', $project_id)->where('active', '1')->get()->first();
                    $kitlist = KitList::where('active', '1')->whereIn('project_id', $projects)->get();
                    foreach ($kitlist as $value) {
                        $kitlist_stock = KitListStock::where('kit_list_id', $value->id)->where('imported_name', $order['component_name'])->get();
                        foreach ($kitlist_stock as $stock) {
                            $stock->update([
                                'component_id' => $comp->id,
                            ]);
                        }
                    }
                }
                $warehouse_stock = $warehouse->warehouse_stock->where("component_id", $componentId)?->first();
                if (empty($warehouse_stock)) {
                    $wh = WarehouseStock::create([
                        'component_id' => $componentId,
                        'warehouse_id' => $warehouse->id,
                        'original_quantity' => '0',
                        'quantity' => '0',
                    ]);
                    $wh->save();
                    $warehouseStockId = $wh->id;
                } else {
                    $warehouseStockId = $warehouse_stock->id;
                }

                $stock_id = array_get($order, 'stock_id');

                $kitlist = KitList::where('active', '1')->whereIn('project_id', $projects)->get();
                $orderedByUser = $order['to_order'];

                $po_header = new PoHeader([
                    'po_code' => $request['po_header']['po_code'],
                    'project_id' => null,
                    'reference' => $request->reference,
                    'user_id' => \Auth::user()->id,
                ]);
                $po_header->save();

                foreach ($kitlist as $value) {
                    $kitlist_stock = KitListStock::where('kit_list_id', $value->id)->where('imported_name', $order['component_imported_name'])->first();
                    if ($kitlist_stock == null) {
                        continue;
                    }

                    $outstanding = $kitlist_stock->getForcastData()->shortfall - $kitlist_stock->getOrderedButNotDeliveredQuantityGlobal();
                    if ($outstanding <= 0) {
                        continue;
                    }

                    //if users orders less then requried

                    if ($orderedByUser <= $outstanding) {
                        $stock_od = PoStockOrdered::create([
                            'user_id' => \Auth::user()->id,
                            'po_header_id' => $po_header->id,
                            'warehouse_stock_id' => $warehouseStockId,
                            'component_id' => $comp->id,
                            'project_id' => $kitlist_stock->kit_list->project->id,
                            'ordered_quantity' => $orderedByUser,
                        ]);
                    }
                    //user has ordered more then requried
                    else {
                        $stock_od = PoStockOrdered::create([
                            'user_id' => \Auth::user()->id,
                            'po_header_id' => $po_header->id,
                            'warehouse_stock_id' => $warehouseStockId,
                            'component_id' => $comp->id,
                            'project_id' => $kitlist_stock->kit_list->project->id,
                            'ordered_quantity' => $outstanding,
                        ]);
                    }

                    $orderedByUser -= $outstanding;
                    if ($orderedByUser <= 0) {
                        break;
                    }
                }

                if ($orderedByUser > 0) {
                    $stock_od = PoStockOrdered::create([
                        'user_id' => \Auth::user()->id,
                        'po_header_id' => $po_header->id,
                        'warehouse_stock_id' => $warehouseStockId,
                        'component_id' => $comp->id,
                        'project_id' => null,
                        'ordered_quantity' => $orderedByUser,
                    ]);

                    $orderedByUser = 0;
                }
            }
            $description = 'A new PO was raised';
            if ($po_header->po_code !== null) {
                if ($po_header->po_code !== '') {
                    $description = 'A new PO was raised with code: ' . $po_header->po_code;
                }
            }
            $kit_list_array = collect([]);
            foreach($orders as $order){
                $kit_list_array->push($order['kit_list_id']);
            }
            $kit_list_array=$kit_list_array->unique();
            foreach($kit_list_array as $kit_list_id){
                if($kit_list_id != null){
                    event(new KitListRefreshRequested($kit_list_id));
                }
            }
            event(new ProjectUpdated($user->id, null, null, null, $po_header->id, null, 'po_raised', $description));

            return redirect()->back()->with([
                'flash_message' => 'Purchase Order successfully placed',
                'flash_message_success' => true,
            ]);
        } else {
            return redirect()->back()->with([
                'flash_message' => 'You are not authorized to perform this action',
                'flash_message_error' => true,
            ]);
        }
    }
    public function storeDelivery(Request $request, $project_id)
    {

        $team = $request->get('team');
        $user = Auth::user();

        $stock_orders = PoStockOrdered::where("po_header_id", $request->po_header_id)->get();

        $delivered_quantity = $request->get('delivered_quantity');
        $Total_Recived = $request->get('delivered_quantity');

        //create entries for projects in db for stock dilevery

        foreach ($stock_orders->whereNotIn('project_id', [null]) as $stock_ordered) {

            if ($delivered_quantity <= 0) {
                break;
            }

            $warehouse_stock = $stock_ordered->warehouse_stock;
            $outstanding = $stock_ordered->getOutstandingQuantity();

            if ($outstanding <= 0) {
                continue;
            }

            if ($outstanding <= $delivered_quantity) {
                $delivered_stock = new PoStockReceived([
                    'user_id' => $user->id,
                    'po_stocks_ordered_id' => $stock_ordered->id,
                    'delivered_quantity' => $outstanding,
                ]);
                $delivered_stock->save();
            } else {
                $delivered_stock = new PoStockReceived([
                    'user_id' => $user->id,
                    'po_stocks_ordered_id' => $stock_ordered->id,
                    'delivered_quantity' => $delivered_quantity,
                ]);
                $delivered_stock->save();
            }

            $delivered_quantity -= $outstanding;
        }

        //warehouse entery added in db
        foreach ($stock_orders->where('project_id', null) as $stock_ordered) {

            $warehouse_stock = $stock_ordered->warehouse_stock;
            if ($delivered_quantity >= 0) {

                $delivered_stock = new PoStockReceived([
                    'user_id' => $user->id,
                    'po_stocks_ordered_id' => $stock_ordered->id,
                    'delivered_quantity' => $delivered_quantity,
                ]);
                $delivered_stock->save();

            }
            break;
        }

        //add stock to warehouse

        $warehouse_stock->update([
            'quantity' => $warehouse_stock->quantity + $Total_Recived,
        ]);

        // update shortfall
        foreach ($stock_orders->whereNotIn('project_id', [null]) as $stock_ordered) {
            $warehouse_stock = $stock_ordered->warehouse_stock;

            if (isset($warehouse_stock)) {

                $kitlist = KitList::where('project_id', $stock_ordered->project_id)->where('active', '1')->get()->first();
                if ($kitlist) {
                    $kitlist_stock = KitListStock::where('kit_list_id', $kitlist->id)->where('imported_name', $request['comp_name'])?->get();
                    foreach ($kitlist_stock as $stock) {
                        if (isset($stock)) {
                            $kitlist_shortfall = $stock->shortfall;
                            if ($kitlist_shortfall - $delivered_quantity >= 0) {
                                $stock->update([
                                    'shortfall' => $kitlist_shortfall - $delivered_quantity,
                                ]);
                            } else {
                                $stock->update([
                                    'shortfall' => 0,
                                ]);
                            }
                        }
                    }
                    if($kitlist->id != null){
                        event(new KitListRefreshRequested($kitlist->id));
                    }
                }
            }
            break;
        }

        return response()->json(["message" => "Success"]);
    }
    public function showOrders(Request $request, $project_id, $header_id)
    {

        $project = Project::findOrFail($project_id);
        $header = PoHeader::findOrFail($header_id);

        return view('purchase-orders.purchase-orders-detail')->with([
            'project_id' => $project->id,
            'header_id' => $header->id,
        ]);
    }

    public function getOrders(Request $request, $project_id, $header_id)
    {

        $project = Project::findOrFail($project_id);
        $header = PoHeader::findOrFail($header_id);

        $ordered_items = $header->po_stocks_ordered;

        return response()->collection($ordered_items, new PoStockOrderedTransformer());
    }

    public function getOrdersAll(Request $request)
    {

        $orders = collect([]);
        $time = 0;

        $projectsAll = Project::get();
        $headers = PoHeader::where("project_id", null)->get();
        // null means order is from global
        foreach ($headers as $h) {
            $ordered_items = $h->po_stocks_ordered;
            $rec_data = collect([]);

            $quantity_data = new static;
            $quantity_data->ordered_quantity = 0;
            $quantity_data->delivered_quantity = 0;

            $last_po_ordered = collect([]);
            foreach ($ordered_items as $po_stock_ordered) {
                $recevied = $po_stock_ordered->po_stocks_received;
                $quantity_data->ordered_quantity += $po_stock_ordered->ordered_quantity;
                $quantity_data->delivered_quantity += $po_stock_ordered->getDeliveredQuantity();
                foreach ($recevied as $r) {
                    $rec_data->push([
                        "user_name" => $r->user->first_name,
                        "delivered_quantity" => $r->delivered_quantity,
                        "created_at" => $r->created_at->format('D M Y'),
                    ]);
                }
                $last_po_ordered->push($po_stock_ordered);

            }
            $time += 1;
            $orders->push([
                'id' => $last_po_ordered?->first()?->id,
                'po_header_id' => $last_po_ordered?->first()?->po_header_id,
                'po_header_code' => $last_po_ordered?->first()?->po_header->po_code,
                'created_at' => $last_po_ordered?->first()?->created_at->format('d M Y'),
                'user_name' => $last_po_ordered?->first()?->user->getFullName(),
                'warehouse_stock_id' => $last_po_ordered?->first()?->warehouse_stock_id,
                'warehouse_id' => $last_po_ordered?->first()?->warehouse_stock?->warehouse->id,
                'warehouse_name' => $last_po_ordered?->first()?->warehouse_stock?->warehouse->name,
                'component_id' => $last_po_ordered?->first()?->component_id,
                'component_name' => $last_po_ordered?->first()?->component?->name,
                'ordered_quantity' => $quantity_data->ordered_quantity,
                'deliveries' => $rec_data,
                'delivered_quantity' => $quantity_data->delivered_quantity,
            ]);
        }

        return response()->json($orders);
    }

}
