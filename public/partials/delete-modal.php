<?php
/** Shared delete confirmation dialog. Include after the page content. */
?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="deleteModalLabel">
                    <i class="bi bi-exclamation-triangle text-danger me-1"></i> Confirm deletion
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">You are about to permanently delete:</p>
                <p class="fw-semibold mb-3" id="deleteTargetName">this record</p>
                <div class="alert alert-warning py-2 small mb-0">
                    This action cannot be undone. Records that are still used by other tables
                    (for example a farm that has planting records) cannot be deleted.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="" id="deleteForm" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i> Delete record
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
