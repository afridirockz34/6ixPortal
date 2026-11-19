<?php
/**
 * One row of the advisor's task board.
 *
 * A partial because the board is rendered twice — grouped by month and
 * grouped by category — and the two views are the same rows seen from two
 * angles. Two copies of this markup would drift, and the half that drifted
 * would be the one nobody was looking at.
 *
 * Expects: $tk (a Six_Tasks::all() row), $view_client_id, $svc_status,
 *          $svc_tcol.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
                <div id="task-<?php echo (int)$tk['id']; ?>" style="display:grid;grid-template-columns:30px 1fr 140px auto;gap:8px;align-items:center;padding:7px 0;border-top:1px solid rgba(255,255,255,0.04)">
                    <div style="font-size:9.5px;color:var(--text3);text-align:center">M<?php echo (int)$tk['month_no']; ?></div>
                    <div style="min-width:0">
                        <div style="font-size:11.5px;line-height:1.35"><?php echo esc_html($tk['title']); ?></div>
                        <?php if($tk['entity'] || empty($tk['published'])): ?>
                        <div style="font-size:9.5px;color:var(--text3)">
                            <?php if($tk['entity']): ?><span style="word-break:break-all"><?php echo esc_html($tk['entity']); ?></span><?php endif; ?>
                            <?php if(empty($tk['published'])): ?> &middot; <span style="color:var(--warning)">draft</span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <select class="six-input six-task-status" data-id="<?php echo (int)$tk['id']; ?>" data-client="<?php echo $view_client_id; ?>"
                            style="font-size:11px;padding:5px 7px;border-color:<?php echo $svc_tcol[$tk['status']] ?? 'var(--border)'; ?>44">
                        <?php foreach($svc_status as $sk => $sl): ?>
                        <option value="<?php echo esc_attr($sk); ?>" <?php selected($tk['status'],$sk); ?>><?php echo esc_html($sl); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="six-btn six-btn-ghost six-btn-sm six-delete-task" data-id="<?php echo (int)$tk['id']; ?>"
                            data-client="<?php echo $view_client_id; ?>" style="font-size:10px;padding:4px 8px">Remove</button>
                </div>
