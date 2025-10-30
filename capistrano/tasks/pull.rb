
# command: cap -T (lists all available capistrano commands)

namespace :pull do

  # command: cap staging pull:db
  # command: cap backup pull:db
  desc 'Download server database to local'
  task :db do
    on roles(:backups) do
      set :db_file, "pcgamesn_live.sql.gz"
      download! "backups/mysql/#{fetch(:db_file)}", fetch(:db_file)
    end
  end

  # command: cap staging pull:db_import
  # command: cap backup pull:db_import
  desc 'Import the server database into local for testing'
  task :db_import do
    set :db_file, "pcgamesn_live.sql.gz"
    run_locally do
      execute "mkdir -p backups && wp db export --add-drop-table - | gzip > backups/local.backup.sql.gz"
      execute "gunzip -c #{fetch(:db_file)} | wp db import -" # && rm #{fetch(:db_file)}
      execute "wp search-replace #{fetch(:site_url)} #{fetch(:local_dev_url)}"
      execute "wp user update caretaker --user_pass='local'"
    end
  end

  # command: cap staging pull:db_revert
  # command: cap backup pull:db_revert
  desc 'Revert last database import'
	task :db_revert do
    run_locally do
			execute "wp db import backups/local.backup.sql.gz"
		end
	end

  # cap staging pull:all
  # cap backup pull:all
  desc 'Run all import tasks'
  task :all do
    invoke 'pull:db'
    invoke 'pull:db_import'
  end

end
