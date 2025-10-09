-- Fix deals_with_users view to remove description column reference
DROP VIEW IF EXISTS `deals_with_users`;

CREATE VIEW `deals_with_users` AS 
SELECT 
    d.id,
    d.buyer_id,
    d.seller_id,
    d.amount,
    d.details,
    d.status,
    d.escrow_amount,
    d.escrow_status,
    d.created_at,
    d.updated_at,
    d.account_id,
    d.conversation_id,
    d.buyer_confirmed_at,
    d.admin_review_status,
    d.admin_reviewed_by,
    d.admin_reviewed_at,
    d.admin_notes,
    buyer.name AS buyer_name,
    buyer.phone AS buyer_phone,
    seller.name AS seller_name,
    seller.phone AS seller_phone,
    acc.game_name,
    '' AS account_description
FROM deals d
LEFT JOIN users buyer ON d.buyer_id = buyer.id
LEFT JOIN users seller ON d.seller_id = seller.id
LEFT JOIN accounts acc ON d.account_id = acc.id;