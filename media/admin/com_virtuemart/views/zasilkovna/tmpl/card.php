<?php
/** @var string $icon */
/** @var string $title */
/** @var string $content */
?>
    <div class="uk-card uk-card-small uk-card-vm mb-4">
        <div class="uk-card-header">
            <div class="uk-card-title">
                <span class="md-color-cyan-600 uk-margin-small-right" uk-icon="icon: <?php echo $icon; ?>; ratio: 1.2"></span>
                <?php echo $title; ?></div>
        </div>
        <div class="uk-card-body">
            <?php echo $content; ?>
        </div>
    </div>
<?php
