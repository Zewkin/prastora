<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_commentmoderation
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$document = Factory::getDocument();
$wa = $document->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate');

?>

<form action="<?php echo Route::_('index.php?option=com_commentmoderation&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="comment-form" class="form-validate">
    <div class="row">
        <div class="col-lg-9">
            <?php echo $this->form->renderField('body'); ?>
        </div>
        <div class="col-lg-3">
            <div class="card mb-3">
                <div class="card-body">
                    <?php echo $this->form->renderField('status'); ?>
                    <?php echo $this->form->renderField('author_name'); ?>
                    <?php echo $this->form->renderField('author_email'); ?>
                    <?php echo $this->form->renderField('created'); ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php echo $this->form->renderField('article_id'); ?>
                    <?php echo $this->form->renderField('section_id'); ?>
                    <?php echo $this->form->renderField('parent_id'); ?>
                    <?php echo $this->form->renderField('anchor_start'); ?>
                    <?php echo $this->form->renderField('anchor_end'); ?>
                    <?php echo $this->form->renderField('anchor_prefix'); ?>
                    <?php echo $this->form->renderField('anchor_suffix'); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <?php echo $this->form->renderField('anchor_exact'); ?>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php
$document->addScriptDeclaration(
    "document.addEventListener('DOMContentLoaded', function () {
        Joomla.submitbutton = function (task) {
            const form = document.getElementById('comment-form');

            if (task === 'comment.cancel' || document.formvalidator.isValid(form)) {
                Joomla.submitform(task, form);
            }
        };
    });"
);
?>

