
namespace :wp do

	desc 'Flush any WordPress caching.'
	task :clear_cache do
		on release_roles(:all) do
			execute "cd #{current_path} && wp cache flush --quiet"
			execute "cd #{current_path} && if wp plugin status w3-total-cache | grep 'Status: Active'; then wp w3-total-cache flush all; fi"
		end
	end

	desc 'Copy the correct WP-Config.'
	task :wp_config do
		on release_roles(:all) do
			execute "cp #{release_path}/wp-configs/#{fetch(:stage)}.php #{release_path}/site-config.php"
		end
	end

	desc 'Run database backup before deploy.'
	task :db_backup do
		on release_roles(:all) do
			execute "cd #{current_path} && wp db export - | gzip > backups/#{release_timestamp}.backup.sql.gz"
		end
	end

end
