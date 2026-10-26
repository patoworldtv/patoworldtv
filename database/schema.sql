-- =========================================================
-- PatoworldTV (FINAL PRODUCTION SCHEMA)
-- =========================================================


-- =========================================================
-- USERS
-- =========================================================
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NULL,
  profile_photo VARCHAR(255) NULL,

  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_users_created (created_at),
  KEY idx_users_admin (is_admin),
  KEY idx_users_blocked (is_blocked)
) ENGINE=InnoDB;

-- =========================================================
-- OAUTH IDENTITIES
-- =========================================================
CREATE TABLE IF NOT EXISTS oauth_identities (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(30) NOT NULL,
  provider_user_id VARCHAR(190) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_provider_user (provider, provider_user_id),
  KEY idx_oauth_user (user_id),

  CONSTRAINT fk_oauth_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- REMEMBER TOKENS
-- =========================================================
CREATE TABLE IF NOT EXISTS remember_tokens (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL UNIQUE,
  verifier_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_rt_user (user_id),
  KEY idx_rt_exp (expires_at),

  CONSTRAINT fk_rem_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- CATEGORIES
-- =========================================================
CREATE TABLE IF NOT EXISTS categories (
  id TINYINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  slug VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (slug, name) VALUES
('news','News'),
('sport','Sport'),
('entertainment','Entertainment'),
('general','General');

-- =========================================================
-- POSTS
-- =========================================================
CREATE TABLE IF NOT EXISTS posts (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  category_id TINYINT UNSIGNED NOT NULL,

  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,

  body MEDIUMTEXT NULL,

  -- legacy single image (optional)
  cover_image VARCHAR(255) NULL,

  -- single featured media (used by your current PHP)
  media_url VARCHAR(255) NULL,
  media_type VARCHAR(20) NULL,

  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_posts_cat_time (category_id, created_at),
  KEY idx_posts_author_time (created_by, created_at),

  FULLTEXT KEY ft_title_body (title, body),

  CONSTRAINT fk_posts_cat
    FOREIGN KEY (category_id)
    REFERENCES categories(id),

  CONSTRAINT fk_posts_user
    FOREIGN KEY (created_by)
    REFERENCES users(id)
) ENGINE=InnoDB;

-- =========================================================
-- POST MEDIA (MULTIPLE MEDIA SUPPORT)
-- =========================================================
CREATE TABLE IF NOT EXISTS post_media (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL,

  media_type ENUM('image','video') NOT NULL,
  media_url VARCHAR(255) NOT NULL,

  mime_type VARCHAR(80) NULL,
  file_size BIGINT UNSIGNED NULL,
  width INT UNSIGNED NULL,
  height INT UNSIGNED NULL,
  duration_seconds INT UNSIGNED NULL,

  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_pm_post_sort (post_id, sort_order),
  KEY idx_pm_type (media_type),

  CONSTRAINT fk_pm_post
    FOREIGN KEY (post_id)
    REFERENCES posts(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- POST LIKES
-- =========================================================
CREATE TABLE IF NOT EXISTS post_likes (
  post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (post_id, user_id),
  KEY idx_pl_user (user_id),
  KEY idx_pl_post (post_id),

  CONSTRAINT fk_pl_post
    FOREIGN KEY (post_id)
    REFERENCES posts(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_pl_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- COMMENTS
-- =========================================================
CREATE TABLE IF NOT EXISTS comments (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,

  content TEXT NOT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_c_post_time (post_id, created_at),
  KEY idx_c_parent (parent_id),
  KEY idx_c_user (user_id),

  CONSTRAINT fk_c_post
    FOREIGN KEY (post_id)
    REFERENCES posts(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_c_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_c_parent
    FOREIGN KEY (parent_id)
    REFERENCES comments(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- COMMENT LIKES
-- =========================================================
CREATE TABLE IF NOT EXISTS comment_likes (
  comment_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (comment_id, user_id),
  KEY idx_cl_user (user_id),
  KEY idx_cl_comment (comment_id),

  CONSTRAINT fk_cl_comment
    FOREIGN KEY (comment_id)
    REFERENCES comments(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_cl_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- OPTIONAL: MAKE USER ADMIN
-- UPDATE users SET is_admin = 1 WHERE email = 'youremail@example.com';
-- =========================================================
