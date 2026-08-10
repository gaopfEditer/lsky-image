<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AlbumRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $albumId = $this->route('id');

        return [
            'name' => [
                'required',
                'max:60',
                'alpha_dash',
                Rule::unique('albums', 'name')
                    ->where(fn ($query) => $query->where('user_id', Auth::id()))
                    ->ignore($albumId),
            ],
            'intro' => 'max:600',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => '名称不能为空',
            'name.max' => '名称字符过长，最大不能超过 60',
            'name.alpha_dash' => '名称只能是字母、数字，短破折号（-）和下划线（_）',
            'name.unique' => '该相册名称已存在',
            'intro.max' => '简介字符过长，最大不能超过 600',
        ];
    }
}
