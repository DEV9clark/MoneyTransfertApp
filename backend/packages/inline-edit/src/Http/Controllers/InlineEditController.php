<?php

namespace MoneyTransfer\InlineEdit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class InlineEditController extends Controller
{
    public function update(Request $request, $model, $id)
    {
        $config = config('inline-edit.models.' . $model);

        if (!$config) {
            return response()->json(['message' => 'Model not supported'], 400);
        }

        $modelClass = $config['class'];
        $item = $modelClass::find($id);

        if (!$item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $field = $request->input('field');
        $value = $request->input('value');

        // Validation
        if (isset($config['rules'][$field])) {
            $request->validate([
                'value' => $config['rules'][$field]
            ]);
        }

        // Handle nested fields (e.g., client.name) - though usually handled by frontend mapping
        // For now, simple update
        if (Schema::hasColumn($item->getTable(), $field)) {
            $item->$field = $value;
            $item->save();
            return response()->json(['success' => true, 'data' => $item]);
        }

        return response()->json(['message' => 'Field not found'], 400);
    }
}
