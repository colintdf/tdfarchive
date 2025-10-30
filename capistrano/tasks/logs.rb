namespace :logs do

	set :tail_length, 100

	desc 'Read Capistrnao deploy log.'
	task :deploy do
		on release_roles(:all) do
			execute "tail -n #{fetch(:tail_length)} #{deploy_path}/revisions.log"
		end
	end

	desc 'Read error logs.'
	task :error do
		on release_roles(:all) do
			execute "tail -n #{fetch(:tail_length)} #{deploy_path}/logs/error.log"
		end
	end

	desc 'Read access logs.'
	task :access do
		on release_roles(:all) do
			execute "tail -n #{fetch(:tail_length)} #{deploy_path}/logs/access.log"
		end
	end

end
