<?php

return [
    'accepted' => 'El campo :attribute debe ser aceptado.',
    'active_url' => 'El campo :attribute no contiene una URL válida.',
    'alpha_dash' => 'El campo :attribute solo puede contener letras, números, guiones y guiones bajos.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña actual no es correcta.',
    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'exists' => 'El :attribute seleccionado no es válido.',
    'in' => 'El :attribute seleccionado no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El :attribute ya está registrado.',
    'attributes' => [
        'name' => 'nombre', 'email' => 'correo electrónico', 'username' => 'usuario',
        'password' => 'contraseña', 'rol_id' => 'rol', 'activo' => 'estado',
        'current_password' => 'contraseña actual', 'password_confirmation' => 'confirmación de contraseña',
        'numero_parte' => 'número de serie', 'codigo' => 'código', 'activo_fijo' => 'activo fijo',
        'numero_serie' => 'número de serie', 'marca' => 'marca', 'modelo' => 'modelo',
        'descripcion' => 'descripción', 'costo_neto_actual' => 'costo neto', 'responsable_nombre' => 'responsable',
        'responsable_email' => 'correo del responsable', 'responsable_departamento' => 'departamento',
        'receptor_nombre' => 'nombre del receptor', 'receptor_email' => 'correo del receptor',
        'receptor_departamento' => 'departamento del receptor', 'motivo' => 'motivo', 'observacion' => 'observación',
        'cantidad' => 'cantidad', 'categoria_id' => 'categoría', 'producto_id' => 'producto',
    ],
];
