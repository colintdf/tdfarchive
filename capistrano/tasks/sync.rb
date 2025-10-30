
namespace :sync do

	desc 'Sync live database to a stage site'
	task :db do
		on roles(:staging) do
			execute "mysqldump #{fetch(:db_name)} | gzip -c > revert_#{fetch(:db_name)}.sql.gz" # Save current db state (just in case we want to revert...)
			execute "zcat backups/mysql/pcgamesn_live.sql.gz | mysql #{fetch(:db_name)}"
			execute "cd #{current_path} && wp search-replace https://www.pcgamesn.com #{fetch(:site_url)}"
		end
	end

	desc 'Revert last database import'
	task :db_revert do
		on roles(:staging) do
			execute "zcat revert_#{fetch(:db_name)}.sql.gz | mysql #{fetch(:db_name)}"
		end
	end
end

after 'sync:db', 'nginx:flush'
after 'sync:db_revert', 'nginx:flush'