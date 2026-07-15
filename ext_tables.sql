CREATE TABLE tx_products_visitedproduct (
    product int(11) DEFAULT '0' NOT NULL,
    view_count int(11) DEFAULT '0' NOT NULL,
    last_viewed int(11) DEFAULT '0' NOT NULL,

    PRIMARY KEY (product)
);
CREATE TABLE tx_products_fe_users_visitedproduct (
    frontend_user int(11) DEFAULT '0' NOT NULL,
    product int(11) DEFAULT '0' NOT NULL,
    view_count int(11) DEFAULT '0' NOT NULL,
    last_viewed int(11) DEFAULT '0' NOT NULL,

    PRIMARY KEY (frontend_user, product)
);
