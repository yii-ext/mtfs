Mtfs
=============
Memory Table From Scratch component. Helps to restore memory tables after mysqld/system restart.
## Add to components section
```php
    'components' => array(
        'Mtfs' => array(
            'class' => 'venror.yii-ext.Mtfs.Mtfs',
            'tables' => array(
                'sourceTableName' => 'destinationTableName',
            ),
        ),
    ),
```
## Use from anywhere run() for restoring tables from config params
```php
    Yii::app()->Mtfs->run();
```

## or memorize($table, $tableMemory) for any other tables memorizing
```php
    Yii::app()->Mtfs->memorize('sourceTableName', 'destinationTableName');
