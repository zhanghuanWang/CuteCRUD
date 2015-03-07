<?php namespace App\Http\Controllers;

use App\Models\Table;
use App\Models\TableRow;
use App\Models\TempModel;
use \DB;
use \Validator;
use \Input;
use \App\Utils;
use \Session;

class TablesController extends Controller
{

    public function uploadFeaturedImage($file)
    {

        $timestamp = time();
        $ext = $file->guessClientExtension();
        $name = $timestamp . "_file." . $ext;

        // move uploaded file from temp to uploads directory
        if ($file->move(public_path() . $this->settings->upload_path, $name)) {
            return $this->settings->upload_path . $name;
        } else {
            return false;
        }
    }

    /**
     * Show Create Page
     *
     * @Get("table/{table_name}/create", as="table.create")
     */
    public function create($table)
    {
        $columns = TableRow::where('table_name', $table)->where('creatable', 1)->get();
        $cols = [];
        return view('tables.create', compact('columns', 'table', 'cols'));
    }

    /**
     * Show edit page
     *
     * @Get("table/{table_name}/edit/{id}", as="table.edit")
     */
    public function edit($table, $needle)
    {

        $columns = TableRow::where('table_name', $table)->where('editable', 1)->get();

        $cols = DB::table($table)->where($this->getNeedle($table), $needle)->first();
        $cols = Utils::object_to_array($cols);

        return view('tables.edit', compact('table', 'needle', 'columns', 'cols'));
    }

    /**
     * Update a row
     *
     * @Post("table/{table_name}/update/{id}", as="table.update")
     */
    public function update($table, $needle)
    {
        $columns = TableRow::where('table_name', $table)->where('editable', 1)->get();

        $rules = [];
        foreach ($columns as $column) {
            $rules[$column->column_name] = $column->edit_rule;
        }

        $v = Validator::make(Input::all(), $rules);

        if ($v->fails()) {
            Session::flash('error_msg', Utils::buildMessages($v->errors()->all()));
            return redirect()->back();
        }

        $input = Input::only(array_keys($rules));
        DB::table($table)->where($this->getNeedle($table), $needle)->update($input);

        Session::flash('success_msg', 'Entry updated successfully');
        return redirect('/table/'.$table.'/list');
    }

    /**
     * Store Row
     *
     * @Post("table/{table_name}/create", as="table.store")
     */
    public function store($table)
    {
        $columns = TableRow::where('table_name', $table)->where('creatable', 1)->get();

        $rules = [];
        foreach ($columns as $column) {
            $rules[$column->column_name] = $column->create_rule;
        }

        $v = Validator::make(Input::all(), $rules);

        if ($v->fails()) {
            Session::flash('error_msg', Utils::buildMessages($v->errors()->all()));
            return Redirect::back()->withErrors($v)->withInput();
        }

        DB::table($table)->insertGetId(Input::except(['_token']));

        Session::flash('success_msg', 'Entry created successfully');
        return redirect('/table/' . $table . '/list');
    }

    /**
     * Show table's rows list
     *
     * @Get("table/{table_name}/list", as="table.show")
     */
    public function all($table_name)
    {
        $table = Table::where('table_name', $table_name)->first();
        $columns_names = TableRow::where('table_name', $table_name)->where('listable', 1)->lists('column_name');
        $columns = DB::table($table_name)->select($columns_names)->paginate(15);
        $ids = DB::table($table_name)->select('id')->paginate(15)->lists('id');

        return view('tables.list', compact('columns_names', 'table', 'columns', 'ids'));
    }

    /**
     * Delete a row
     *
     * @Get("table/{table_name}/delete/{id}", as="table.delete")
     */
    public function delete($table, $needle)
    {
        DB::table($table)->where($this->getNeedle($table), $needle)->delete();

        Session::flash('success_msg', 'Entry deleted successfully');
        return redirect("/table/{$table}/list");
    }

    /**
     * Get table's needle
     *
     */
    protected function getNeedle($table)
    {
        return Table::where('table_name', $table)->select('needle')->first()->needle;
    }

}
