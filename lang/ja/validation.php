<?php

/*
 * Japanese messages for the validation rules this application uses.
 * Rules not listed here fall back to Laravel's English messages.
 */
return [
    'array' => ':attributeは配列で指定してください。',
    'before_or_equal' => ':attributeには:date以前の日付を指定してください。',
    'boolean' => ':attributeははい・いいえで指定してください。',
    'date' => ':attributeには正しい日付を指定してください。',
    'distinct' => ':attributeに重複した値があります。',
    'email' => ':attributeには正しいメールアドレスを入力してください。',
    'exists' => '選択された:attributeは正しくありません。',
    'in' => '選択された:attributeは正しくありません。',
    'integer' => ':attributeは整数で指定してください。',
    'max' => [
        'array' => ':attributeは:max個以下で指定してください。',
        'string' => ':attributeは:max文字以内で入力してください。',
    ],
    'min' => [
        'numeric' => ':attributeは:min以上で指定してください。',
    ],
    'regex' => ':attributeの形式が正しくありません。',
    'required' => ':attributeを入力してください。',
    'required_if' => ':otherが:valueの場合、:attributeを入力してください。',
    'string' => ':attributeは文字列で入力してください。',
    'unique' => 'この:attributeは既に使われています。',

    'values' => [
        'form.information_status' => [
            'recorded' => '確認・記録済み',
        ],
    ],

    'attributes' => [
        'barcode' => 'バーコード',
        'category' => '分類',
        'email' => 'メールアドレス',
        'ids' => '保存した商品',
        'password' => 'パスワード',
        'q' => '検索語',
    ],
];
