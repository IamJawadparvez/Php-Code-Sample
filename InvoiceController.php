<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use App\Invoice;
use App\InvoiceRecord;
use App\Settings;
use App\PaymentMethod;

//this is invoice controller in which multiple functionality are perform 
class InvoiceController extends Controller
{

   // this is construct that automatically call to authentication
    public function __construct()
    {
        $this->middleware('auth');
    }
    //this is index function that show all the invoices that i had made
    public function index()
    {
        if(!Auth::user()->hasrole('Admin|HR')) {
            return redirect()->route('index');
        }

        $invoices = Invoice::all();
        $grandtotal = [];
        foreach ($invoices as $hello) {
            foreach ($hello->invoiceRecords as $invo)
            {
                $subtotal = $invo->quantity * $invo->price;
                $grandtotal[] += $subtotal;
            }
        }

        return view('invoices.index', compact('invoices', 'grandtotal'));
    }
    

    //this function return the invoice form in which we can submit the invoice into database
    public function create()
    {
        $inv_prefix = Settings::where('name', 'inv_prefix')->first()->value;
        $inv_sn = Settings::where('name', 'inv_prefix_sn')->first()->value;
        $increment = $inv_sn+1;

        $invoice_no = $inv_prefix.$inv_sn;

        $payment_methods = PaymentMethod::all();
        return view('invoices.create', compact('invoice_no', 'payment_methods', 'increment'));
    }

    //this function work as a store procedure in which invoice are stored into the database

    public function store(Request $request)
    {
        $name = $request->get('name');
        $phone = $request->get('phone');
        $email = $request->get('email');
        $invo_discount_percentage = $request->get('discount_percentage');
        $invo_discount_amount = $request->get('discount_amount');

        $invoice_no = $request->get('invoice_sn');
        $status = $request->get('status');
        $date = $request->get('date');
        $due_date = $request->get('due_date');
        $payment_method = $request->get('payment_method');
        $tax = $request->get('tax');


        $inv_no = Settings::where('name', 'inv_prefix_sn')->first();
        $inv_no->value = $request->get('increment');
        $inv_no->save();

        $invoice = new Invoice();
        $invoice->name = $name;
        $invoice->phone = $phone;
        $invoice->email = $email;
        $invoice->discount_percentage = $invo_discount_percentage;
        $invoice->discount_amount = $invo_discount_amount;
        $invoice->tax = $tax;
        $invoice->invoice_no = $invoice_no;
        $invoice->status = $status;
        $invoice->date = $date;
        $invoice->payment_method = $payment_method;

        $invoice->save();

        $pdfId = $invoice->id;

        $item = $request->get('item');
        $quantity = $request->get('quantity');
        $price = $request->get('price');
        $description = $request->get('description');
        for($i = 0; $i <= $request->get('input'); $i++)
        {
            $invoice = new InvoiceRecord();
            $invoice->invoice_id = $pdfId;
            $invoice->item = $item[$i];
            $invoice->quantity = $quantity[$i];
            $invoice->price = $price[$i];
            $invoice->description = $description[$i];
            $invoice->save();

            $invoices[] = $invoice->id;
        };

        $multiple_invo = InvoiceRecord::whereIn('id', $invoices)->get();

        $grandtotal = 0;

        foreach($multiple_invo as $invoice)
        {
            $subtotal = $invoice->quantity * $invoice->price;;
            $grandtotal += $subtotal;
        }

        $success = "Invoice Added";
        return view('invoices.show_invoices', with(compact('name', 'phone', 'email', 'multiple_invo', 'grandtotal', 'invo_discount_percentage', 'invo_discount_amount', 'invoice_no', 'status', 'date', 'due_date', 'payment_method', 'pdfId', 'tax')));
    }
// this function only show the record of invoice which we selected
    public function show($id)
    {
        $invoice = Invoice::find($id);

        $grandtotal = 0;
        foreach ($invoice->invoiceRecords as $invo)
        {
            $subtotal = $invo->quantity * $invo->price;
            $grandtotal += $subtotal;
        }

        return view('invoices.show-record', with(compact('invoice', 'grandtotal')));
    }
   //this function work as edit functionality
    public function edit($id)
    {
        $invoice = Invoice::find($id);
        $payment_methods = PaymentMethod::all();

        $grandtotal = 0;
        foreach ($invoice->invoiceRecords as $invo)
        {
            $subtotal = $invo->quantity * $invo->price;
            $grandtotal += $subtotal;
        }

        return view('invoices.edit', with(compact('invoice', 'grandtotal', 'payment_methods')));
    }

    //this function update the data of invoices that we have changes in it

    public function update(Request $request, $id)
    {
        $inovice = Invoice::find($id);
        $inovice->name = $request->get('name');
        $inovice->phone = $request->get('phone');
        $inovice->email = $request->get('email');
        $inovice->discount_percentage = $request->get('discount_percentage');
        $inovice->discount_amount = $request->get('discount_amount');
        $inovice->tax = $request->get('tax');
        $inovice->date = $request->get('date');
        $inovice->payment_method = $request->get('payment_method');
        $inovice->status = $request->get('status');
        $inovice->paid = $request->get('paid');

        $inovice->save();

        $item = $request->get('item');
        $quantity = $request->get('quantity');
        $price = $request->get('price');
        $description = $request->get('description');
        $inv_id = $request->get('inv_id');

        for($i = 0; $i < $request->get('input'); $i++)
        {
            $invoice = InvoiceRecord::find($inv_id[$i]);
            $invoice->item = $item[$i];
            $invoice->quantity = $quantity[$i];
            $invoice->price = $price[$i];
            $invoice->description = $description[$i];
            $invoice->save();

        };

        $success = 'Record updated';
        return back()->with('success', $success);
    }

    //this function works as invoice converted into pdf

    public function createInvoicePDF($id)
    {
        $invoice = Invoice::find($id);

        $total = 0;
        foreach ($invoice->invoiceRecords as $records)
        {
            $subtotal = $records->quantity * $records->price;;
            $total += $subtotal;
        }

        $filename = 'Invoice.pdf';
        $mpdf = new \Mpdf\Mpdf();
        $html = \View::make('invoices.pdf')->with(compact('invoice', 'total'));
        $html = $html->render();
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename, 'I');
    }

}
