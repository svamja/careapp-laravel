<?php

return [

    "databases" => [
        "careapp_profiles_db",
        "careapp_passions_db",
        "careapp_messages_db",
        "careapp_log_db", 
    ],

    "categories" => [
        "Basic Needs",
        "Health",
        "Social",
        "Children & Youth",
        "Women",
        "Seniors & Specially Abled",
        "Animals",
        "Safety",
        "Environment",
        "Spiritual",
        "Happiness",
        "Others",
    ],

    "design_docs" => [
        [
            "db" => "careapp_passions_db",
            "_id" => "_design/categories",
            "language" => "javascript",
            "views" => [
                "by_order" => [
                    "map" => "function(doc) { if(doc.type == 'category') { emit([doc.order], doc); }  }"
                ]
            ]
        ],
        [
            "db" => "careapp_passions_db",
            "_id" => "_design/sub_categories",
            "language" => "javascript",
            "views" => [
                "by_category" => [
                    "map" => "function(doc) { if(doc.type == 'sub_category') { emit([doc.category_id], doc); }  }"
                ]
            ]
        ],
        [
            "db" => "careapp_passions_db",
            "_id" => "_design/passions",
            "language" => "javascript",
            "views" => [
                "by_passion" => [
                    "map" => "function(doc) { if(doc.type == 'passion') { emit([doc._id], doc); }  }"
                ]
            ]
        ],
        [
            "db" => "careapp_passions_db",
            "_id" => "_design/cities",
            "language" => "javascript",
            "views" => [
                "cities" => [
                    "map" => "function(doc) { if(doc.type == 'city') { emit([doc._id], doc); }  }"
                ]
            ]
        ],
        [
            "db" => "careapp_passions_db",
            "_id" => "_design/countries",
            "language" => "javascript",
            "views" => [
                "countries" => [
                    "map" => "function(doc) { if(doc.type == 'country') { emit([doc._id], doc); }  }"
                ]
            ]
        ],
        [
            "db" => "careapp_profiles_db",
            "_id" => "_design/profiles",
            "language" => "javascript",
            "views" => [
                "by_passion" => [
                    "map" => "function(doc) { if(doc.type != 'profile' || !doc.passions) return; for(var i = 0, len = doc.passions.length; i < len; i++) emit(doc.passions[i].id, doc); }"
                ],
                "by_city_passion" => [
                    "map" => "function(doc) { if(doc.type != 'profile' || !doc.passions || !doc.city) return; for(var i = 0, len = doc.passions.length; i < len; i++) emit([doc.city, doc.passions[i].id], doc); }"
                ]
            ],
            "filters" => [
                "by_interest" => "function(doc, req) { return doc.city === req.query.city; }"
            ]
        ],
        [
            "db" => "careapp_messages_db",
            "_id" => "_design/messages",
            "language" => "javascript",
            "views" => [
                "by_passion_ts" => [
                    "map" => "function(doc) { if(doc.passion_id && doc.posted_on) { emit([doc.passion_id, doc.posted_on], doc); } }"
                ]
            ]
        ],
        
    ],

];