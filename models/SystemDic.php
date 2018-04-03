<?php

namespace ciniran\dic\models;

use ciniran\dic\components\DicTools;
use ciniran\dic\service\SysDic;
use Yii;
use yii\db\ColumnSchemaBuilder;
use yii\db\Migration;
use yii\db\Schema;

/**
 * This is the model class for table "{{%system_dic}}".
 *
 * @property integer $id
 * @property integer $pid
 * @property string $name
 * @property string $value
 * @property integer $status
 * @property integer $sort
 * @property SystemDic $p
 * @property SystemDic[] $systemDics
 */
class SystemDic extends \yii\db\ActiveRecord
{
    public $subKeys;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%system_dic}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['pid', 'status','sort'], 'integer'],
            [['name','value'],'required'],
            [['name', 'value'], 'string', 'max' => 255],
            [['pid'], 'exist', 'skipOnError' => true, 'targetClass' => SystemDic::className(), 'targetAttribute' => ['pid' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('dic', 'ID'),
            'pid' => Yii::t('dic', 'Pid'),
            'name' => Yii::t('dic', 'Name'),
            'value' => Yii::t('dic', 'Value'),
            'status' => Yii::t('dic', 'Status'),
            'sort'  => Yii::t('dic', 'Sort'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getP()
    {
        return $this->hasOne(SystemDic::className(), ['id' => 'pid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSystemDics()
    {
        return $this->hasMany(SystemDic::className(), ['pid' => 'id']);
    }

    /**
     * 检查在同一子类中是否已存在相同的值
     * @param $model
     * @return bool
     */
    public function isExist($model)
    {
        $res = self::findAll(['pid' => $model->pid, 'name' => $model->name,'value'=>$model->value]);
        if ($res) {
            return true;
        }
        return false;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes); // TODO: Change the autogenerated stub
        DicTools::cleanSystemDic();
    }
    public function check()
    {
        $tableName = Yii::$app->db->tablePrefix . "system_dic";
        return Yii::$app->db->createCommand("SHOW TABLES LIKE '".$tableName."'")->queryAll();
    }
    public function initTable()
    {
        $tableOptions = null;
        if (Yii::$app->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
        $migrations = new Migration();
        $columns = [
            'id' => Schema::TYPE_PK,
            'pid'=> Schema::TYPE_INTEGER,
            'name' => $migrations->string(255),
            'value' => $migrations->string(255),
            'status' => $migrations->integer(1)->defaultValue(1),
            'sort' => $migrations->integer(2)->defaultValue(0),
            'FOREIGN KEY ([[pid]]) REFERENCES ' . self::tableName() . ' ([[id]])'.
            $this->buildFkClause('ON DELETE NO ACTION', 'ON UPDATE NO ACTION')
        ];
        Yii::$app->db->createCommand()->createTable(self::tableName(), $columns, $tableOptions)->execute();
        foreach ($columns as $column => $type) {
            if ($type instanceof ColumnSchemaBuilder && $type->comment !== null) {
                Yii::$app->db->createCommand()->addCommentOnColumn(self::tableName(), $column, $type->comment)->execute();
            }
        }
        $migrations->batchInsert(self::tableName(), [
            'id','pid','name','value','status'
        ], [
            ['1', null, '基础状态', 'base_status', '1'],
            ['2', '1', '是', '1', '1'],
            ['3', '1', '否', '0', '1'],
            ['4', null, '操作状态', 'do_status', '1'],
            ['5', '4', '启用', '1', '1'],
            ['6', '4', '禁用', '0', '1'],
        ]);
    }

    protected function buildFkClause($delete = '', $update = '')
    {
        if ($this->isMSSQL()) {
            return '';
        }

        if ($this->isOracle()) {
            return ' ' . $delete;
        }

        return implode(' ', ['', $delete, $update]);
    }

    /**
     * @return bool
     */
    protected function isMSSQL()
    {
        return Yii::$app->db->driverName === 'mssql' || Yii::$app->db->driverName === 'sqlsrv' || Yii::$app->db->driverName === 'dblib';
    }
    protected function isOracle()
    {
        return Yii::$app->db->driverName === 'oci';
    }

    public function getStatusType()
    {
        return SysDic::getKey('base_status');
    }

    public function afterDelete()
    {
        DicTools::cleanSystemDic();
        return parent::afterDelete();
    }
}
