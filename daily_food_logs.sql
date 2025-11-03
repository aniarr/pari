-- Table for storing daily food consumption records
CREATE TABLE IF NOT EXISTS daily_food_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    food_name VARCHAR(255) NOT NULL,
    serving_size DECIMAL(10,2) NOT NULL,
    serving_unit VARCHAR(50) NOT NULL,
    calories DECIMAL(10,2) NOT NULL,
    protein DECIMAL(10,2) NOT NULL,
    carbs DECIMAL(10,2) NOT NULL,
    fats DECIMAL(10,2) NOT NULL,
    log_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(log_date),
    FOREIGN KEY (user_id) REFERENCES register(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for storing daily nutrition targets
CREATE TABLE IF NOT EXISTS daily_nutrition_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_calories DECIMAL(10,2) NOT NULL,
    target_protein DECIMAL(10,2) NOT NULL,
    target_carbs DECIMAL(10,2) NOT NULL,
    target_fats DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY(user_id),
    FOREIGN KEY (user_id) REFERENCES register(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;