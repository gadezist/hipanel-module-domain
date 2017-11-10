<?php

use hipanel\modules\domain\grid\DomainGridView;
use hipanel\modules\domain\menus\DomainBulkActionsMenu;
use hipanel\widgets\IndexPage;

/**
 * @var $this \yii\web\View
 * @var $dataProvider \hiqdev\hiart\ActiveDataProvider
 */

$this->title = Yii::t('hipanel', 'Domains');
$this->params['subtitle'] = array_filter(Yii::$app->request->get($model->formName(), [])) ? Yii::t('hipanel', 'filtered list') : Yii::t('hipanel', 'full list');
$this->params['breadcrumbs'][] = $this->title;

?>

<?php $page = IndexPage::begin(compact('model', 'dataProvider')) ?>
    <?= $page->setSearchFormData() ?>

    <?php $page->beginContent('sorter-actions') ?>
        <?= $page->renderSorter([
            'attributes' => [
                'domain', 'note', 'client', 'seller',
                'created_date', 'expires', 'id',
            ],
        ]) ?>
    <?php $page->endContent() ?>

    <?php $page->beginContent('bulk-actions') ?>
        <?= DomainBulkActionsMenu::widget([], [
            'encodeLabels' => false,
            'itemOptions' => [
                'tag' => false,
            ],
        ]) ?>
    <?php $page->endContent() ?>

    <?php $page->beginContent('table') ?>
        <?php $page->beginBulkForm() ?>
            <?= DomainGridView::widget([
                'boxed' => false,
                'dataProvider' => $dataProvider,
                'filterModel'  => $model,
                'columns' => $representationCollection->getByName($uiModel->representation)->getColumns(),
            ]) ?>
        <?php $page->endBulkForm() ?>
    <?php $page->endContent() ?>
<?php $page->end() ?>
