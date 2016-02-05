<?php
/**
 * @var array
 * @var $state string
 */
use hipanel\modules\domain\models\Domain;
use yii\helpers\Html;
use yii\helpers\Url;

?>

<div class="domain-iso-line <?= Domain::setIsotopeFilterValue($line['zone']) ?> <?= $state ?> <?= $requestedDomain === $line['full_domain_name'] ? 'popular' : $requestedDomain ?>">
<div
    class="domain-line <?= ($state) ? 'checked' : '' ?>"
    data-domain="<?= $line['full_domain_name'] ?>">
    <div class="col-md-6 col-sm-12 col-xs-12">
        <?php if ($state) : ?>
            <span class="domain-img"><i class="fa fa-globe fa-lg"></i></span>
        <?php else : ?>
            <span class="domain-img"><i class="fa fa-circle-o-notch fa-spin fa-lg"></i></span>
        <?php endif; ?>

        <?php if ($state === 'available') : ?>
            <span class="domain-name"><?= $line['domain'] ?></span><span
                class="domain-zone">.<?= $line['zone'] ?></span>
        <?php elseif ($state === 'unavailable') : ?>
            <span class="domain-name muted"><?= $line['domain'] ?></span><span
                class="domain-zone muted">.<?= $line['zone'] ?></span>
        <?php else : ?>
            <span class="domain-name muted"><?= $line['domain'] ?></span><span
                class="domain-zone muted">.<?= $line['zone'] ?></span>
        <?php endif; ?>
    </div>
    <div class="col-md-4 col-sm-12 col-xs-12 text-center">
        <span class="domain-price">
            <?php if ($state === 'available') : ?>
                <!--del>0.00 €</del-->
                <?= Yii::$app->formatter->format($line['resource']->price, ['currency', $line['resource']->currency]) ?>
                <span class="domain-price-year">/<?= Yii::t('app', 'year') ?></span>

            <?php elseif ($state === 'unavailable') : ?>
                <span class="domain-taken">
                    <?= Yii::t('app', 'Domain is not available') ?>
                </span>
            <?php else : ?>

            <?php endif; ?>
        </span>
    </div>
    <div class="col-md-2 col-sm-12 col-xs-12">
        <?php if ($state === 'available') : ?>
            <?= Html::a('<i class="fa fa-cart-plus fa-lg"></i>&nbsp; ' . Yii::t('app', 'Add to cart'), ['add-to-cart-registration', 'name' => $line['full_domain_name']], [
                'data-pjax' => 0,
                'class' => 'btn btn-flat bg-olive add-to-cart-button',
                'data-loading-text' => '<i class="fa fa-circle-o-notch fa-spin fa-lg"></i>&nbsp;&nbsp;' . Yii::t('hipanel/domain', 'Loading') . '...',
                'data-complete-text' => '<i class="fa fa-check fa-lg"></i>&nbsp;&nbsp;' . Yii::t('hipanel/domain', 'In cart'),
                'data-domain-url' => Url::to(['add-to-cart-registration', 'name' => $line['full_domain_name']]),
            ]) ?>
        <?php elseif ($state === 'unavailable') : ?>
            <?= Html::a('<i class="fa fa-search"></i>&nbsp; ' . Yii::t('app', 'WHOIS'), 'https://ahnames.com/ru/search/whois/#' . $line['full_domain_name'], ['target' => '_blank', 'class' => 'btn btn-default btn-flat']) ?>
        <?php else : ?>

        <?php endif; ?>
    </div>
</div>
</div>
